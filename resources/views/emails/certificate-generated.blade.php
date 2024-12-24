<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your Certificate is Ready!</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #666;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Your Certificate is Ready!</h1>
    </div>

    <div class="content">
        <p>Dear {{ $certificate->recipient_name }},</p>

        <p>We are pleased to inform you that your certificate has been generated and is ready for download.</p>

        <p>Certificate Details:</p>
        <ul>
            <li>Template: {{ $certificate->template->name }}</li>
            <li>Format: {{ strtoupper($certificate->format) }}</li>
            <li>Generated: {{ $certificate->generated_at->format('Y-m-d H:i') }}</li>
        </ul>

        <p>You can find your certificate attached to this email. You can also view or download your certificate using the buttons below:</p>

        <center>
            <div style="margin: 20px 0;">
                <a href="{{ route('certificates.show', $certificate) }}" class="button" style="background-color: #3b82f6; margin-right: 10px;">View Certificate</a>
                <a href="{{ route('certificates.download', $certificate) }}" class="button" style="background-color: #10b981;">Download Certificate</a>
            </div>
        </center>
    </div>

    <div class="footer">
        <p>This is an automated message. Please do not reply to this email.</p>
        <p>If you have any questions, please contact our support team.</p>
    </div>

    {!! $trackingPixel !!}
</body>
</html>
