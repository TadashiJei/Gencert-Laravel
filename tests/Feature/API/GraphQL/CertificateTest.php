<?php

namespace Tests\Feature\API\GraphQL;

use Tests\TestCase;
use App\Models\User;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;

class CertificateTest extends TestCase
{
    use RefreshDatabase, WithFaker, MakesGraphQLRequests;

    protected $user;
    protected $template;
    protected $certificates;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->template = CertificateTemplate::factory()->create();
        $this->certificates = Certificate::factory()
            ->count(5)
            ->create(['template_id' => $this->template->id]);
    }

    /** @test */
    public function it_can_query_single_certificate()
    {
        $certificate = $this->certificates->first();

        $response = $this->graphQL(/** @lang GraphQL */ '
            query($id: ID!) {
                certificate(id: $id) {
                    id
                    certificateNumber
                    recipientName
                    recipientEmail
                    status
                    template {
                        id
                        name
                    }
                }
            }
        ', [
            'id' => $certificate->id
        ]);

        $response->assertJson([
            'data' => [
                'certificate' => [
                    'id' => (string) $certificate->id,
                    'certificateNumber' => $certificate->certificate_number,
                    'recipientName' => $certificate->recipient_name,
                    'recipientEmail' => $certificate->recipient_email,
                    'status' => $certificate->status,
                    'template' => [
                        'id' => (string) $this->template->id,
                        'name' => $this->template->name
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_query_certificates_with_pagination()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                certificates(first: 2) {
                    edges {
                        node {
                            id
                            certificateNumber
                        }
                    }
                    pageInfo {
                        hasNextPage
                        endCursor
                    }
                    totalCount
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'certificates' => [
                    'edges' => [
                        [
                            'node' => [
                                'id' => (string) $this->certificates[0]->id,
                                'certificateNumber' => $this->certificates[0]->certificate_number
                            ]
                        ],
                        [
                            'node' => [
                                'id' => (string) $this->certificates[1]->id,
                                'certificateNumber' => $this->certificates[1]->certificate_number
                            ]
                        ]
                    ],
                    'pageInfo' => [
                        'hasNextPage' => true
                    ],
                    'totalCount' => 5
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_create_certificate()
    {
        $input = [
            'templateId' => $this->template->id,
            'recipientName' => $this->faker->name,
            'recipientEmail' => $this->faker->email,
            'customFields' => ['field1' => 'value1'],
            'metadata' => ['meta1' => 'value1']
        ];

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation($input: CreateCertificateInput!) {
                createCertificate(input: $input) {
                    certificate {
                        id
                        recipientName
                        recipientEmail
                        customFields
                        metadata
                    }
                }
            }
        ', [
            'input' => $input
        ]);

        $response->assertJson([
            'data' => [
                'createCertificate' => [
                    'certificate' => [
                        'recipientName' => $input['recipientName'],
                        'recipientEmail' => $input['recipientEmail'],
                        'customFields' => $input['customFields'],
                        'metadata' => $input['metadata']
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_update_certificate()
    {
        $certificate = $this->certificates->first();
        $input = [
            'id' => $certificate->id,
            'recipientName' => 'Updated Name',
            'customFields' => ['field2' => 'value2']
        ];

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation($input: UpdateCertificateInput!) {
                updateCertificate(input: $input) {
                    certificate {
                        id
                        recipientName
                        customFields
                    }
                }
            }
        ', [
            'input' => $input
        ]);

        $response->assertJson([
            'data' => [
                'updateCertificate' => [
                    'certificate' => [
                        'id' => (string) $certificate->id,
                        'recipientName' => 'Updated Name',
                        'customFields' => ['field2' => 'value2']
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_delete_certificate()
    {
        $certificate = $this->certificates->first();

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation($id: ID!) {
                deleteCertificate(id: $id) {
                    success
                    message
                }
            }
        ', [
            'id' => $certificate->id
        ]);

        $response->assertJson([
            'data' => [
                'deleteCertificate' => [
                    'success' => true,
                    'message' => 'Certificate deleted successfully'
                ]
            ]
        ]);

        $this->assertDatabaseMissing('certificates', [
            'id' => $certificate->id
        ]);
    }

    /** @test */
    public function it_can_revoke_certificate()
    {
        $certificate = $this->certificates->first();
        $input = [
            'id' => $certificate->id,
            'reason' => 'Test revocation'
        ];

        $response = $this->graphQL(/** @lang GraphQL */ '
            mutation($input: RevokeCertificateInput!) {
                revokeCertificate(input: $input) {
                    certificate {
                        id
                        status
                        revokedReason
                        revokedAt
                    }
                }
            }
        ', [
            'input' => $input
        ]);

        $response->assertJson([
            'data' => [
                'revokeCertificate' => [
                    'certificate' => [
                        'id' => (string) $certificate->id,
                        'status' => 'REVOKED',
                        'revokedReason' => 'Test revocation'
                    ]
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_filter_certificates()
    {
        Certificate::factory()->create([
            'template_id' => $this->template->id,
            'status' => 'EXPIRED'
        ]);

        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                certificates(
                    filter: { status: [EXPIRED] }
                ) {
                    edges {
                        node {
                            id
                            status
                        }
                    }
                    totalCount
                }
            }
        ');

        $response->assertJson([
            'data' => [
                'certificates' => [
                    'edges' => [
                        [
                            'node' => [
                                'status' => 'EXPIRED'
                            ]
                        ]
                    ],
                    'totalCount' => 1
                ]
            ]
        ]);
    }

    /** @test */
    public function it_can_sort_certificates()
    {
        $response = $this->graphQL(/** @lang GraphQL */ '
            query {
                certificates(
                    sort: [{ field: CREATED_AT, direction: DESC }]
                ) {
                    edges {
                        node {
                            id
                            createdAt
                        }
                    }
                }
            }
        ');

        $certificates = $response->json('data.certificates.edges');
        $this->assertTrue(
            strtotime($certificates[0]['node']['createdAt']) >
            strtotime($certificates[1]['node']['createdAt'])
        );
    }
}
