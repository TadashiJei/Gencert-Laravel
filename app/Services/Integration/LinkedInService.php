<?php

namespace App\Services\Integration;

use App\Models\Certificate;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class LinkedInService
{
    protected $client;
    protected $apiKey;
    protected $apiSecret;
    protected $baseUrl = 'https://api.linkedin.com/v2';

    public function __construct()
    {
        $this->client = new Client();
        $this->apiKey = config('services.linkedin.client_id');
        $this->apiSecret = config('services.linkedin.client_secret');
    }

    /**
     * Share certificate achievement on LinkedIn
     */
    public function shareCertificate(Certificate $certificate, User $user)
    {
        try {
            $accessToken = $user->oauthProviders()
                ->where('provider', 'linkedin')
                ->first()
                ->access_token;

            // Get user profile
            $profile = $this->getUserProfile($accessToken);

            // Create share content
            $shareContent = [
                'author' => "urn:li:person:{$profile['id']}",
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => "I'm excited to announce that I've earned a new certificate!\n\n" .
                                    "Certificate: {$certificate->certificate_number}\n" .
                                    "Issued by: CertificateHub\n" .
                                    "Date: {$certificate->issued_at->format('F j, Y')}\n\n" .
                                    "#certification #achievement #professional"
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            // Post to LinkedIn
            $response = $this->client->post("{$this->baseUrl}/ugcPosts", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ],
                'json' => $shareContent
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error("Failed to share certificate on LinkedIn: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Add certificate to LinkedIn profile
     */
    public function addCertificateToProfile(Certificate $certificate, User $user)
    {
        try {
            $accessToken = $user->oauthProviders()
                ->where('provider', 'linkedin')
                ->first()
                ->access_token;

            // Get user profile
            $profile = $this->getUserProfile($accessToken);

            // Create certification entry
            $certificationData = [
                'name' => $certificate->template->name,
                'authority' => 'CertificateHub',
                'number' => $certificate->certificate_number,
                'startDate' => [
                    'year' => $certificate->issued_at->year,
                    'month' => $certificate->issued_at->month
                ]
            ];

            if ($certificate->expires_at) {
                $certificationData['endDate'] = [
                    'year' => $certificate->expires_at->year,
                    'month' => $certificate->expires_at->month
                ];
            }

            // Add to profile
            $response = $this->client->post("{$this->baseUrl}/certifications", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ],
                'json' => $certificationData
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error("Failed to add certificate to LinkedIn profile: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get user's LinkedIn profile
     */
    protected function getUserProfile(string $accessToken)
    {
        try {
            $response = $this->client->get("{$this->baseUrl}/me", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'X-Restli-Protocol-Version' => '2.0.0'
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error("Failed to get LinkedIn profile: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create LinkedIn Learning course mapping
     */
    public function mapToCourse(Certificate $certificate, string $courseId)
    {
        try {
            // Get course details from LinkedIn Learning API
            $courseDetails = $this->getCourseDetails($courseId);

            // Create mapping
            $mapping = [
                'certificate_id' => $certificate->id,
                'course_id' => $courseId,
                'course_name' => $courseDetails['title'],
                'course_description' => $courseDetails['description'],
                'skills' => $courseDetails['skills'],
                'duration' => $courseDetails['duration']
            ];

            // Store mapping in database
            // This would require a new model and migration for course mappings
            return $mapping;
        } catch (\Exception $e) {
            Log::error("Failed to map certificate to LinkedIn Learning course: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get course details from LinkedIn Learning
     */
    protected function getCourseDetails(string $courseId)
    {
        try {
            $response = $this->client->get("https://api.linkedin.com/v2/learningAssets/{$courseId}", [
                'headers' => [
                    'Authorization' => "Bearer " . config('services.linkedin.learning_api_key'),
                    'Content-Type' => 'application/json'
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error("Failed to get LinkedIn Learning course details: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify learning completion
     */
    public function verifyLearningCompletion(User $user, string $courseId)
    {
        try {
            $accessToken = $user->oauthProviders()
                ->where('provider', 'linkedin')
                ->first()
                ->access_token;

            $response = $this->client->get("https://api.linkedin.com/v2/learningHistory", [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json'
                ],
                'query' => [
                    'q' => 'criteria',
                    'courseId' => $courseId,
                    'status' => 'COMPLETED'
                ]
            ]);

            $completion = json_decode($response->getBody(), true);
            return !empty($completion['elements']);
        } catch (\Exception $e) {
            Log::error("Failed to verify LinkedIn Learning completion: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate skills assessment
     */
    public function generateSkillsAssessment(Certificate $certificate)
    {
        try {
            // Get skills from certificate template
            $skills = $certificate->template->skills ?? [];

            // For each skill, create an assessment
            $assessments = [];
            foreach ($skills as $skill) {
                $assessment = [
                    'skill' => $skill,
                    'questions' => $this->getSkillQuestions($skill),
                    'passingScore' => 70,
                    'timeLimit' => 30 // minutes
                ];
                $assessments[] = $assessment;
            }

            return $assessments;
        } catch (\Exception $e) {
            Log::error("Failed to generate skills assessment: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get questions for skill assessment
     */
    protected function getSkillQuestions(string $skill)
    {
        try {
            $response = $this->client->get("https://api.linkedin.com/v2/skillAssessments", [
                'headers' => [
                    'Authorization' => "Bearer " . config('services.linkedin.assessment_api_key'),
                    'Content-Type' => 'application/json'
                ],
                'query' => [
                    'skill' => $skill
                ]
            ]);

            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error("Failed to get skill assessment questions: " . $e->getMessage());
            throw $e;
        }
    }
}
