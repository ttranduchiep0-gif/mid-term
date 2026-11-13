CREATE DATABASE IF NOT EXISTS QLNhanSu
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE QLNhanSu;

CREATE TABLE IF NOT EXISTS Users (
  admin_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_email VARCHAR(255) NOT NULL,
  admin_password VARCHAR(255) NOT NULL,
  images VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (admin_id),
  UNIQUE KEY uq_users_admin_email (admin_email)
);

INSERT IGNORE INTO Users (admin_email, admin_password, images) VALUES
('nguyen.an@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL),
('tran.binh@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.', NULL);
