# CertificateHub

A powerful certificate generation and management system built with Laravel.

## Features

- 🎓 Certificate Generation
  - Individual and bulk generation
  - Multiple output formats (PDF, PNG, SVG)
  - Custom templates with dynamic fields
  - Preview functionality

- 📧 Email Delivery
  - Automatic email notifications
  - Customizable email templates
  - Delivery tracking
  - Scheduled sending

- 📊 Analytics Dashboard
  - Generation statistics
  - Usage trends
  - Template analytics
  - User activity tracking

- 👥 User Management
  - Role-based access control
  - User impersonation
  - Activity logging
  - API token management

- 🔒 Security
  - Authentication & authorization
  - Rate limiting
  - Audit logging
  - Data encryption

- 🛠 API Integration
  - RESTful API
  - Token authentication
  - Comprehensive documentation
  - Rate limiting

## Documentation

- [Installation Guide](docs/installation.md)
- [User Guide](docs/user-guide.md)
- [Administrator Guide](docs/admin-guide.md)
- [API Documentation](resources/docs/api/v1/openapi.yaml)

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Node.js 16+
- Composer 2.x
- Redis (optional)

## Quick Start

1. Clone the repository:
```bash
git clone https://github.com/your-org/certificatehub.git
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Set up environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Configure database and run migrations:
```bash
php artisan migrate
```

5. Build assets:
```bash
npm run build
```

6. Start the development server:
```bash
php artisan serve
```

Visit `http://localhost:8000` to access the application.

## Deployment

Use the deployment script:
```bash
./deploy.sh
```

For detailed deployment instructions, see the [Installation Guide](docs/installation.md).

## Backup

Use the backup script:
```bash
./backup.sh
```

For detailed backup procedures, see the [Administrator Guide](docs/admin-guide.md).

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## Security

If you discover any security-related issues, please email security@certificatehub.com instead of using the issue tracker.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

- Documentation: https://docs.certificatehub.com
- Issues: https://github.com/your-org/certificatehub/issues
- Email: support@certificatehub.com
