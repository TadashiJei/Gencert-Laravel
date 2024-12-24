# CertificateHub Analytics & Reporting API Documentation

## Overview

The CertificateHub Analytics & Reporting API provides comprehensive access to analytics data, reporting features, and visualization capabilities. This GraphQL-based API enables you to retrieve metrics, generate reports, and access real-time statistics about certificates, users, and system activities.

## Authentication

All API requests require authentication using a Bearer token:

```http
Authorization: Bearer <your_access_token>
```

To obtain an access token, use the authentication endpoint:

```graphql
mutation {
  login(input: {
    email: "user@example.com",
    password: "your_password"
  }) {
    token
    user {
      id
      name
      email
    }
  }
}
```

## Base URL

```
https://your-domain.com/graphql
```

## Dashboard Metrics

### Get Dashboard Metrics

Retrieves comprehensive dashboard metrics including certificate statistics, user metrics, and activity data.

**Query:**
```graphql
query DashboardMetrics($dateRange: DateRangeInput) {
  dashboardMetrics(dateRange: $dateRange) {
    certificates {
      total
      active
      expired
      revoked
      byTemplate {
        template {
          id
          name
        }
        count
      }
      expiringSoon
    }
    users {
      totalUsers
      activeUsers
      userRoles {
        role
        count
      }
      topUsers {
        user {
          id
          name
        }
        count
      }
    }
    activities {
      totalActivities
      byType {
        type
        count
      }
      recent {
        id
        activityType
        user {
          name
        }
        createdAt
      }
    }
  }
}
```

**Parameters:**
```json
{
  "dateRange": {
    "startDate": "2024-01-01T00:00:00Z",
    "endDate": "2024-12-31T23:59:59Z"
  }
}
```

**Response Example:**
```json
{
  "data": {
    "dashboardMetrics": {
      "certificates": {
        "total": 1250,
        "active": 980,
        "expired": 200,
        "revoked": 70,
        "byTemplate": [
          {
            "template": {
              "id": "1",
              "name": "Professional Certificate"
            },
            "count": 450
          }
        ],
        "expiringSoon": 45
      },
      "users": {
        "totalUsers": 500,
        "activeUsers": 320
      },
      "activities": {
        "totalActivities": 2500
      }
    }
  }
}
```

## Charts and Visualizations

### Certificate Trends Chart

Retrieves certificate issuance trends over time.

**Query:**
```graphql
query CertificateTrends($dateRange: DateRangeInput!) {
  certificateTrendsChart(dateRange: $dateRange) {
    type
    data
    options
  }
}
```

**Response Example:**
```json
{
  "data": {
    "certificateTrendsChart": {
      "type": "line",
      "data": {
        "labels": ["2024-01", "2024-02", "2024-03"],
        "datasets": [
          {
            "label": "Total Certificates",
            "data": [100, 150, 200]
          }
        ]
      },
      "options": {
        "responsive": true
      }
    }
  }
}
```

### User Activity Heatmap

Visualizes user activity patterns across different times.

**Query:**
```graphql
query ActivityHeatmap($dateRange: DateRangeInput!) {
  userActivityHeatmap(dateRange: $dateRange) {
    type
    data
    options
  }
}
```

## Report Management

### Generate Report

Creates a new report based on specified metrics and filters.

**Mutation:**
```graphql
mutation GenerateReport($config: ReportConfigInput!) {
  generateReport(config: $config) {
    id
    name
    type
    config
    data
  }
}
```

**Input Example:**
```json
{
  "config": {
    "name": "Monthly Certificate Analysis",
    "type": "certificate_metrics",
    "metrics": ["certificates", "users", "activities"],
    "startDate": "2024-01-01T00:00:00Z",
    "endDate": "2024-01-31T23:59:59Z",
    "filters": {
      "templateId": "1",
      "status": ["active", "expired"]
    }
  }
}
```

### Schedule Report

Sets up automated report generation.

**Mutation:**
```graphql
mutation ScheduleReport($id: ID!, $schedule: ReportScheduleInput!) {
  scheduleReport(id: $id, schedule: $schedule) {
    id
    name
    schedule
    nextRun
  }
}
```

**Input Example:**
```json
{
  "id": "report_id",
  "schedule": {
    "frequency": "weekly",
    "day": 1,
    "hour": 9,
    "minute": 0
  }
}
```

### Export Report

Exports a report in various formats.

**Mutation:**
```graphql
mutation ExportReport($id: ID!, $format: String!) {
  exportReport(id: $id, format: $format)
}
```

## Real-time Statistics

### Get Real-time Stats

Retrieves current system statistics.

**Query:**
```graphql
query {
  realTimeStats {
    activeUsers
    certificatesIssued
    recentActivities {
      id
      type
      user {
        name
      }
      timestamp
    }
  }
}
```

## Performance Metrics

### Get Performance Metrics

Retrieves system performance data.

**Query:**
```graphql
query Performance($dateRange: DateRangeInput) {
  performanceMetrics(dateRange: $dateRange) {
    averageProcessingTime
    errorRate
    peakUsageTimes
    resourceUtilization
  }
}
```

## Error Handling

The API uses standard GraphQL error responses:

```json
{
  "errors": [
    {
      "message": "Error message",
      "locations": [
        {
          "line": 2,
          "column": 3
        }
      ],
      "path": ["fieldName"],
      "extensions": {
        "code": "ERROR_CODE"
      }
    }
  ]
}
```

Common error codes:
- `UNAUTHENTICATED`: Invalid or missing authentication
- `FORBIDDEN`: Insufficient permissions
- `BAD_USER_INPUT`: Invalid input parameters
- `INTERNAL_SERVER_ERROR`: Server-side error

## Rate Limiting

- 1000 requests per hour per API key
- Real-time endpoints: 60 requests per minute
- Export endpoints: 10 requests per minute

## Best Practices

1. **Pagination:**
   - Use pagination for large datasets
   - Implement cursor-based pagination for better performance

```graphql
query {
  certificates(first: 10, after: "cursor") {
    edges {
      node {
        id
        number
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

2. **Batch Operations:**
   - Use batching for multiple operations
   - Implement DataLoader for efficient data fetching

3. **Caching:**
   - Cache responses when appropriate
   - Use ETags for cache validation

4. **Error Handling:**
   - Always check for errors in responses
   - Implement proper error handling in your client

## Examples

### JavaScript/TypeScript (Apollo Client):

```typescript
import { ApolloClient, InMemoryCache } from '@apollo/client';

const client = new ApolloClient({
  uri: 'https://your-domain.com/graphql',
  cache: new InMemoryCache(),
  headers: {
    'Authorization': `Bearer ${token}`
  }
});

// Get dashboard metrics
const { data } = await client.query({
  query: gql`
    query DashboardMetrics($dateRange: DateRangeInput) {
      dashboardMetrics(dateRange: $dateRange) {
        certificates {
          total
          active
        }
      }
    }
  `,
  variables: {
    dateRange: {
      startDate: "2024-01-01T00:00:00Z",
      endDate: "2024-12-31T23:59:59Z"
    }
  }
});
```

### PHP (Lighthouse):

```php
use GraphQL\Client;

$client = new Client(
    'https://your-domain.com/graphql',
    ['Authorization' => 'Bearer ' . $token]
);

// Generate report
$response = $client->mutation(
    'GenerateReport',
    [
        'config' => [
            'name' => 'Monthly Report',
            'type' => 'certificate_metrics',
            'metrics' => ['certificates', 'users'],
            'startDate' => '2024-01-01T00:00:00Z',
            'endDate' => '2024-01-31T23:59:59Z'
        ]
    ]
);
```

## Support

For API support or questions:
- Email: api-support@certificatehub.com
- Documentation: https://docs.certificatehub.com
- API Status: https://status.certificatehub.com

## Changelog

### v1.0.0 (2024-01-01)
- Initial release of Analytics & Reporting API
- Basic metrics and reporting features

### v1.1.0 (2024-02-01)
- Added real-time statistics
- Enhanced performance metrics
- Improved error handling
