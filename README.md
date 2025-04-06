# Terral Online Production System API

A PHP backend API for an eCommerce platform that allows users to customize and order printed/branded products such as apparel, accessories, books, and home décor.

## Features

- User Authentication & Role Management (JWT-based)
- Product Management System
- Order Management System
- Payment & Billing Module (M-Pesa Integration)
- Inventory Management System
- Reporting & Analytics Dashboard

## Technology Stack

- Backend: Core PHP (without frameworks)
- Database: MySQL
- Authentication: JWT-based authentication
- File Uploads: PHP file handling for product images
- Payments: M-Pesa API integration
- Security: Prepared statements, input validation, and password hashing
- Architecture: RESTful API following MVC (Model-View-Controller) pattern

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Composer (for dependencies)
- SSL for secure communication (required for M-Pesa API in production)

## Installation

1. Clone the repository:
   ```
   git clone https://github.com/your-username/terral-online-production-system.git
   cd terral-online-production-system
   ```

2. Install dependencies using Composer:
   ```
   composer install
   ```

3. Configure environment settings:
   ```
   cp .env.example .env
   ```
   Then edit the `.env` file with your database credentials, JWT settings, and other configuration options.

4. Create a MySQL database:
   ```
   mysql -u your_username -p
   ```
   ```sql
   CREATE DATABASE terral_db;
   ```

5. Import the database schema:
   ```
   mysql -u your_username -p terral_db < api/database/terral_db.sql
   ```

6. Ensure the upload directories are writable:
   ```
   chmod -R 755 api/uploads
   chmod -R 755 api/logs
   ```

7. Configure your web server to point to the project directory. Example for Apache:
   ```apache
   <VirtualHost *:80>
       ServerName terral.local
       DocumentRoot /path/to/terral-online-production-system
       
       <Directory /path/to/terral-online-production-system>
           AllowOverride All
           Require all granted
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/terral_error.log
       CustomLog ${APACHE_LOG_DIR}/terral_access.log combined
   </VirtualHost>
   ```

8. Update the API URL and Frontend URL in the `.env` file:
   ```
   API_URL=http://terral.local/api
   FRONTEND_URL=http://terral.local
   ```

## M-Pesa Integration Setup

1. Create an account on the [Safaricom Developer Portal](https://developer.safaricom.co.ke/)
2. Create a new app and get your Consumer Key and Consumer Secret
3. Update the M-Pesa configuration in your `.env` file:
   ```
   MPESA_CONSUMER_KEY=your_mpesa_consumer_key
   MPESA_CONSUMER_SECRET=your_mpesa_consumer_secret
   MPESA_PASSKEY=your_mpesa_passkey
   MPESA_SHORTCODE=your_mpesa_shortcode
   MPESA_CALLBACK_URL=https://your-domain.com/api/payments/mpesa-callback
   MPESA_ENV=sandbox  # Change to "production" for live environment
   ```

4. For testing, you can use the Safaricom M-Pesa sandbox. For production, you'll need to go through the Safaricom onboarding process.

## Email and SMS Notifications

1. Configure SMTP settings in the `.env` file for email notifications:
   ```
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USERNAME=your_email@gmail.com
   SMTP_PASSWORD=your_app_password
   SMTP_FROM_EMAIL=your_email@gmail.com
   SMTP_FROM_NAME="Terral Online Production System"
   ```

2. For SMS notifications via Twilio, update the following settings:
   ```
   TWILIO_ACCOUNT_SID=your_twilio_account_sid
   TWILIO_AUTH_TOKEN=your_twilio_auth_token
   TWILIO_FROM_NUMBER=your_twilio_phone_number
   ```

## API Endpoints

### Authentication

- `POST /api/auth/login` - User login
- `POST /api/auth/verify` - Verify JWT token
- `POST /api/users/register` - User registration
- `POST /api/users/reset-password` - Reset password

### Users

- `GET /api/users` - Get all users (Admin only)
- `GET /api/users/{id}` - Get a specific user
- `POST /api/users` - Create a new user (Admin only)
- `PUT /api/users/{id}` - Update a user
- `DELETE /api/users/{id}` - Delete a user (Admin only)

### Products

- `GET /api/products` - Get all products
- `GET /api/products/{id}` - Get a specific product
- `POST /api/products` - Create a new product (Admin only)
- `PUT /api/products/{id}` - Update a product (Admin only)
- `DELETE /api/products/{id}` - Delete a product (Admin only)
- `POST /api/products/{id}/upload` - Upload product image (Admin only)
- `POST /api/products/{id}/customize` - Upload customization image for a product

### Orders

- `GET /api/orders` - Get all orders (Admin only)
- `GET /api/orders/{id}` - Get a specific order
- `POST /api/orders` - Create a new order
- `PUT /api/orders/{id}` - Update an order
- `PUT /api/orders/{id}/status` - Update order status (Admin only)
- `PUT /api/orders/{id}/cancel` - Cancel an order

### Payments

- `POST /api/payments/mpesa-init` - Initiate M-Pesa payment
- `POST /api/payments/card-init` - Initiate card payment
- `POST /api/payments/bank-init` - Initiate bank transfer
- `POST /api/payments/cod-init` - Initiate cash on delivery
- `POST /api/payments/callback` - Payment gateway callback handler
- `GET /api/payments/methods` - Get available payment methods
- `GET /api/payments/{id}` - Get payment details
- `POST /api/payments/{id}/invoice` - Generate invoice for an order

### Inventory

- `GET /api/inventory` - Get inventory list (Admin only)
- `GET /api/inventory/low-stock` - Get low stock products (Admin only)
- `PUT /api/inventory/{id}` - Update product stock (Admin only)
- `POST /api/inventory/bulk-import` - Bulk import products from CSV (Admin only)

### Reports

- `GET /api/reports/dashboard` - Get dashboard statistics (Admin only)
- `GET /api/reports/sales` - Get sales report (Admin only)
- `GET /api/reports/products` - Get product performance report (Admin only)
- `GET /api/reports/customers` - Get customer insights (Admin only)
- `GET /api/reports/categories` - Get revenue by category (Admin only)

## Default Admin Credentials

- Email: admin@terral.com
- Password: admin123

## Authentication

All API endpoints requiring authentication must include the JWT token in the Authorization header:
```
Authorization: Bearer YOUR_JWT_TOKEN
```

## Response Format

All API responses follow a standard JSON format:

```json
{
  "success": true,
  "message": "Success message",
  "data": {
    // Response data here
  }
}
```

For errors:

```json
{
  "success": false,
  "message": "Error message",
  "errors": {
    // Detailed error information
  }
}
```

## Security Features

- JWT authentication for secure API access
- Environment variables for sensitive configuration
- Prepared statements to prevent SQL injection
- Input validation and sanitization
- Password hashing using PHP's password_hash() function
- CSRF protection
- Rate limiting
- Comprehensive error logging

## Development

For development mode, set `DEBUG=true` in your `.env` file. This will enable detailed error reporting.

## Testing

You can use the included Postman collection to test the API endpoints:

1. Import the collection from `docs/Terral_API.postman_collection.json`
2. Set up your environment variables in Postman
3. Run the tests

## License

This project is licensed under the MIT License - see the LICENSE file for details. 