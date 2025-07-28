-- Base de données simplifiée pour commencer
CREATE DATABASE IF NOT EXISTS immich_gallery;
USE immich_gallery;

-- Table des galeries
CREATE TABLE galleries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    slug VARCHAR(255) UNIQUE,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table des images avec géolocalisation
CREATE TABLE gallery_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT,
    immich_asset_id VARCHAR(255),
    caption TEXT,
    author VARCHAR(255),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    location_name VARCHAR(255),
    unesco_site_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (gallery_id) REFERENCES galleries(id)
);

-- Table des sites UNESCO
CREATE TABLE unesco_sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    description TEXT,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    country VARCHAR(100),
    category VARCHAR(100)
);

-- Table des lieux d'intérêt
CREATE TABLE places (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    type VARCHAR(100), -- hotel, restaurant, monument, etc.
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    address TEXT,
    rating DECIMAL(2,1)
);
