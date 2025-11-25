# Social Media Platform

A comprehensive social media management platform built with Laravel and Vue.js that enables businesses to manage multiple social media accounts across Instagram, Facebook, and LinkedIn.

## Features

- **Multi-Platform Management**: Connect and manage Instagram, Facebook, and LinkedIn accounts
- **AI-Powered Content Generation**: Automated content creation using OpenAI GPT
- **Brand Guidelines**: Platform-specific tone of voice and content customization
- **Content Approval Workflow**: Review and approve AI-generated content before publishing
- **Calendar Scheduling**: Visual calendar interface for content planning
- **Manual Posting**: Create and publish content manually when needed
- **Engagement Analytics**: Track performance metrics across all platforms
- **AI Learning**: System adapts content generation based on engagement performance

## Technology Stack

- **Backend**: Laravel 12 with PHP 8.3+
- **Frontend**: Vue.js 3 SPA with Composition API
- **Database**: MySQL with optimized indexing
- **Queue System**: Redis for background job processing
- **Authentication**: Laravel Sanctum for API authentication
- **AI Integration**: OpenAI GPT API for content generation
- **Social Media APIs**: Official APIs for Instagram, Facebook, and LinkedIn

## Installation

### Prerequisites

- PHP 8.3 or higher
- Composer
- Node.js and npm
- MySQL database
- Redis (optional, for queues and caching)

### Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd social-media-platform
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**
   ```bash
   npm install
   ```

4. **Environment configuration**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your `.env` file**
   ```env
   # Database
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=your_database
   DB_USERNAME=your_username
   DB_PASSWORD=your_password

   # Social Media APIs
   INSTAGRAM_CLIENT_ID=your_instagram_client_id
   INSTAGRAM_CLIENT_SECRET=your_instagram_client_secret
   FACEBOOK_CLIENT_ID=your_facebook_client_id
   FACEBOOK_CLIENT_SECRET=your_facebook_client_secret
   LINKEDIN_CLIENT_ID=your_linkedin_client_id
   LINKEDIN_CLIENT_SECRET=your_linkedin_client_secret

   # OpenAI
   OPENAI_API_KEY=your_openai_api_key
   ```

6. **Run database migrations**
   ```bash
   php artisan migrate
   ```

7. **Build frontend assets**
   ```bash
   npm run build
   ```

8. **Start the application**
   ```bash
   php artisan serve
   ```

## Configuration

### Social Media API Setup

1. **Instagram/Facebook**: Create a Facebook Developer account and set up Instagram Basic Display API
2. **LinkedIn**: Create a LinkedIn Developer account and set up LinkedIn Marketing API
3. **OpenAI**: Get an API key from OpenAI platform

### Queue Configuration

For background job processing (recommended for production):

```bash
# Start queue worker
php artisan queue:work

# Or use supervisor for production
```

### Scheduled Tasks

Add to your crontab for Laravel scheduler:

```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

## Usage

1. **Register/Login**: Create an account or log in
2. **Connect Social Accounts**: Link your Instagram, Facebook, and LinkedIn accounts
3. **Set Brand Guidelines**: Configure tone of voice and content preferences for each platform
4. **Generate Content**: Let AI create monthly content based on your guidelines
5. **Review & Approve**: Review generated content and approve for publishing
6. **Schedule Posts**: Use the calendar interface to schedule content
7. **Monitor Performance**: Track engagement metrics and optimize strategy

## API Endpoints

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout

### Social Accounts
- `GET /api/social-accounts` - List connected accounts
- `POST /api/social-accounts/connect/{platform}` - Connect social account
- `DELETE /api/social-accounts/{account}` - Disconnect account

### Content Management
- `GET /api/posts` - List posts
- `POST /api/posts` - Create post
- `PUT /api/posts/{post}` - Update post
- `POST /api/posts/{post}/approve` - Approve draft post

### Analytics
- `GET /api/analytics` - Get analytics data
- `GET /api/analytics/post/{post}` - Get post-specific analytics

## Testing

The application includes comprehensive property-based tests that validate correctness properties:

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
```

## Security Features

- JWT-based authentication with Laravel Sanctum
- Rate limiting on API endpoints
- CORS configuration for secure cross-origin requests
- Input validation and sanitization
- Secure OAuth token storage
- HTTPS enforcement in production

## Performance Optimizations

- Database query optimization with proper indexing
- Redis caching for frequently accessed data
- Asset compilation and minification
- OPcache configuration for PHP
- Queue-based background processing

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## License

This project is licensed under the MIT License.

## Support

For support and questions, please refer to the documentation or create an issue in the repository.