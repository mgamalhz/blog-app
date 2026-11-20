# blog-app

A RESTful API built with Laravel for managing blog posts, categories, tags, comments, and token-based user authentication.

## Requirements

- PHP >= 8.2
- Composer
- MySQL

## Local Setup

1- Clone the repository
git clone <repository-url> blog-app
cd blog-app

2- Install PHP dependencies
composer install

3- Configure environment variables
copy .env.example .env
(Update 'DB_DATABASE', 'DB_USERNAME', and 'DB_PASSWORD' in .env to match your local MySQL credentials)
php artisan key:generate

4- Create the MySQL database file and run migrations
CREATE DATABASE IF NOT EXISTS "blog_app"
php artisan migrate

5- Start the local development server:
php artisan serve

## Running Tests

The test suite uses PHPUnit with an in-memory SQLite database. Run all tests with: php artisan test

## Authentication (Laravel Sanctum)

Protected endpoints require a Bearer token in the 'Authorization' header.

1- Register or Login to get a token
curl -X POST http://127.0.0/api/login
-H "Accept: application/json",
-H "Content-Type: application/json",
-d '{"email": "user@example.com", "password": "password"}

```

2- Use the returned token in subsequent requests like create new post
curl -X POST http://127.0.0/api/posts
  -H "Accept: application/json",
  -H "Authorization: Bearer <YOUR_ACCESS_TOKEN>",
  -H "Content-Type: application/json",
  -d '{"title": "My First Post", "body": "Post content here", "category_id": 1}
```
