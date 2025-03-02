-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Anamakine: localhost:8889
-- Üretim Zamanı: 02 Mar 2025, 21:02:01
-- Sunucu sürümü: 5.7.39
-- PHP Sürümü: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `sitedb`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `admin_accounts`
--

CREATE TABLE `admin_accounts` (
  `id` int(25) NOT NULL,
  `user_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `series_id` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remember_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires` datetime DEFAULT NULL,
  `admin_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registration_no` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `institution` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `specialization` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `admin_accounts`
--

INSERT INTO `admin_accounts` (`id`, `user_name`, `password`, `series_id`, `remember_token`, `expires`, `admin_type`, `name`, `email`, `phone`, `title`, `registration_no`, `department`, `institution`, `specialization`, `created_at`, `updated_at`, `last_login`) VALUES
(8, 'kkuhackathon25', '$2y$10$RnDwpen5c8.gtZLaxHEHDOKWY77t/20A4RRkWBsjlPuu7Wmy0HyBu', 'y2ZwiIm30p3w2DCO', '$2y$10$3G/Q.7uEiiO97qbxb6z.xuJ/U2/K/Hxt2jl0PRgBbq23aw5WkJP46', '2025-04-01 20:45:00', 'admin', 'İbrahim Taşkıran', 'ibosta@kku.edu.tr', '+90 318 357 33 01', 'Veteriner Hekim', 'VHK-2025-1234', 'Veteriner Fakültesi', 'Kırıkkale Üniversitesi', 'Küçük Hayvan Klinikleri', '2024-12-31 21:00:00', '2025-03-02 20:45:00', '2025-03-01 01:57:14');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `medications`
--

CREATE TABLE `medications` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'İlaç adı',
  `description` text COMMENT 'İlaç açıklaması',
  `unit` varchar(20) NOT NULL COMMENT 'Birim (tablet, ml, vb.)',
  `min_stock` int(11) DEFAULT '10' COMMENT 'Minimum stok seviyesi',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL COMMENT 'Hayvan türü (kedi, köpek, vb.)',
  `breed` varchar(100) DEFAULT NULL COMMENT 'Irkı',
  `age` int(11) DEFAULT NULL COMMENT 'Yaşı (ay olarak)',
  `gender` enum('erkek','dişi') DEFAULT NULL,
  `owner_name` varchar(100) NOT NULL COMMENT 'Sahibinin adı',
  `owner_phone` varchar(20) NOT NULL COMMENT 'Sahibinin telefonu',
  `owner_email` varchar(100) DEFAULT NULL COMMENT 'Sahibinin e-postası',
  `owner_address` text COMMENT 'Sahibinin adresi',
  `notes` text COMMENT 'Ek notlar',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Reçeteyi yazan veteriner',
  `diagnosis` varchar(255) NOT NULL COMMENT 'Tanı',
  `diagnosis_details` text COMMENT 'Tanı detayları',
  `notes` text COMMENT 'Ek notlar',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL,
  `prescription_id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `dosage` varchar(100) NOT NULL COMMENT 'Doz bilgisi',
  `daily_usage` varchar(100) NOT NULL COMMENT 'Günlük kullanım',
  `usage_period` varchar(100) NOT NULL COMMENT 'Kullanım süresi',
  `notes` text COMMENT 'Ek notlar'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stock`
--

CREATE TABLE `stock` (
  `id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `quantity` float NOT NULL DEFAULT '0',
  `reorder_level` float NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stock_history`
--

CREATE TABLE `stock_history` (
  `id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `quantity_change` float NOT NULL,
  `type` enum('add','subtract','adjust') NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `user_name` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` varchar(50) DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` tinyint(1) DEFAULT '1',
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `user_name`, `password`, `name`, `email`, `phone`, `role`, `created_at`, `updated_at`, `status`, `last_login`) VALUES
(1, 'admin', '$2y$10$xyYrpHmBqXD1MS4RPqTqBuqPRTcI92wuBUHpW5cxlHpkWYc1VdOC6', 'İbrahim Taşkıran', 'admin@example.com', NULL, 'admin', '2025-02-28 22:52:42', '2025-03-01 14:52:47', 1, NULL),
(2, 'test', '$2y$10$OQKsQUKhT/iGZ5sEUZfSfOu9QgskyWRHnmTk.ptvWkuNhblD/IW1u', 'ibrahim test', 'test@test.com', '05555555555', 'user', '2025-03-02 17:09:35', '2025-03-02 17:09:35', 1, NULL);

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `admin_accounts`
--
ALTER TABLE `admin_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_name` (`user_name`);

--
-- Tablo için indeksler `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Tablo için indeksler `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Tablo için indeksler `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `prescription_id` (`prescription_id`),
  ADD KEY `medication_id` (`medication_id`);

--
-- Tablo için indeksler `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medication_id` (`medication_id`);

--
-- Tablo için indeksler `stock_history`
--
ALTER TABLE `stock_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medication_id` (`medication_id`),
  ADD KEY `fk_stock_history_user` (`user_id`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_name` (`user_name`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `admin_accounts`
--
ALTER TABLE `admin_accounts`
  MODIFY `id` int(25) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Tablo için AUTO_INCREMENT değeri `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `stock_history`
--
ALTER TABLE `stock_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescription_items_ibfk_2` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`);

--
-- Tablo kısıtlamaları `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `stock_history`
--
ALTER TABLE `stock_history`
  ADD CONSTRAINT `fk_stock_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `stock_history_ibfk_1` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
