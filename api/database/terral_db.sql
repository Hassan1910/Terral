-- Create Terral Online Production System Database Schema

-- Drop database if it exists
-- DROP DATABASE IF EXISTS terral_db;

-- Create database
CREATE DATABASE IF NOT EXISTS terral_db CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

-- Use the database
USE terral_db;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'customer') NOT NULL DEFAULT 'customer',
  phone VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(100) NULL,
  state VARCHAR(100) NULL,
  postal_code VARCHAR(20) NULL,
  country VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role (role)
) ENGINE=InnoDB;

-- Create categories table
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB;

-- Create products table
CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10, 2) NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  is_customizable TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active', 'inactive', 'out_of_stock') NOT NULL DEFAULT 'active',
  category_id INT NOT NULL,
  sku VARCHAR(100) NULL,
  weight DECIMAL(10, 2) NULL,
  dimensions VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_status (status),
  INDEX idx_stock (stock),
  INDEX idx_price (price),
  INDEX idx_category_id (category_id),
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create product_categories table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS product_categories (
  product_id INT NOT NULL,
  category_id INT NOT NULL,
  PRIMARY KEY (product_id, category_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create orders table
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  total_price DECIMAL(10, 2) NOT NULL,
  status ENUM('pending', 'processing', 'shipped', 'delivered', 'canceled') NOT NULL DEFAULT 'pending',
  payment_status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  payment_method VARCHAR(50) NULL,
  payment_id VARCHAR(100) NULL,
  shipping_address VARCHAR(255) NOT NULL,
  shipping_city VARCHAR(100) NOT NULL,
  shipping_state VARCHAR(100) NULL,
  shipping_postal_code VARCHAR(20) NULL,
  shipping_country VARCHAR(100) NOT NULL,
  shipping_phone VARCHAR(20) NOT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_id (user_id),
  INDEX idx_status (status),
  INDEX idx_payment_status (payment_status),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- Create order_items table
CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(255) NOT NULL,
  quantity INT NOT NULL,
  price DECIMAL(10, 2) NOT NULL,
  customization_color VARCHAR(50) NULL,
  customization_size VARCHAR(50) NULL,
  customization_image VARCHAR(255) NULL,
  customization_text TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
  INDEX idx_order_id (order_id),
  INDEX idx_product_id (product_id)
) ENGINE=InnoDB;

-- Create payments table
CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  amount DECIMAL(10, 2) NOT NULL,
  payment_method VARCHAR(50) NOT NULL,
  transaction_id VARCHAR(255) NULL,
  status ENUM('pending', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
  payment_date TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  INDEX idx_order_id (order_id),
  INDEX idx_status (status),
  INDEX idx_transaction_id (transaction_id)
) ENGINE=InnoDB;

-- Insert default admin user (password: admin123)
INSERT INTO users (first_name, last_name, email, password, role) 
VALUES ('Admin', 'User', 'admin@terral.com', '$2y$10$FKgDvSvR6C7lSMY7C4Bhwu4XwNqtTkRCAEGQPJNOB3G.4d/pSvwgy', 'admin');

-- Insert some categories
INSERT INTO categories (name, description) VALUES
('Apparel', 'Clothing and wearable items'),
('Accessories', 'Additional items to complement your style'),
('Books', 'Printed books and publications'),
('Home Decor', 'Items to decorate and personalize your living space'),
('Promotional', 'Marketing and promotional materials'),
('Stationery', 'Paper products and office supplies');

-- Insert some products
INSERT INTO products (name, description, price, stock, is_customizable, status) VALUES
('Custom T-Shirt', 'High-quality cotton T-shirt that can be customized with your design.', 25.99, 100, 1, 'active'),
('Custom Mug', 'Ceramic mug that can be customized with your photo or text.', 15.99, 75, 1, 'active'),
('Business Cards (100 pcs)', 'Premium business cards printed on 350gsm card stock.', 19.99, 50, 1, 'active'),
('Custom Wall Canvas', 'Canvas print for your wall, available in multiple sizes.', 45.99, 30, 1, 'active'),
('Branded Notebook', 'Hardcover notebook with custom logo or design.', 12.99, 80, 1, 'active'),
('Custom Phone Case', 'Protective phone case with your custom design.', 22.99, 60, 1, 'active'),
('Promotional Pens (50 pcs)', 'Ballpoint pens with your company logo.', 35.99, 40, 1, 'active'),
('Photo Book', 'Hardcover photo book with your custom photos.', 49.99, 25, 1, 'active'),
('Custom Calendar', 'Wall calendar with your photos for each month.', 29.99, 45, 1, 'active'),
('Personalized Tote Bag', 'Cotton tote bag with custom design or text.', 18.99, 70, 1, 'active');

-- Associate products with categories
INSERT INTO product_categories (product_id, category_id) VALUES
(1, 1), -- T-Shirt in Apparel
(2, 4), -- Mug in Home Decor
(3, 5), -- Business Cards in Promotional
(3, 6), -- Business Cards in Stationery
(4, 4), -- Wall Canvas in Home Decor
(5, 6), -- Notebook in Stationery
(6, 2), -- Phone Case in Accessories
(7, 5), -- Promotional Pens in Promotional
(7, 6), -- Promotional Pens in Stationery
(8, 3), -- Photo Book in Books
(9, 6), -- Calendar in Stationery
(10, 1), -- Tote Bag in Apparel
(10, 2); -- Tote Bag in Accessories