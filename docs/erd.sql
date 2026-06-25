-- =====================================================================
-- Tax Processing System (TPS) - Skema Database (ERD dalam bentuk .sql)
-- Engine: MySQL 8 / MariaDB (utf8mb4)
-- =====================================================================
--
-- RINGKASAN RELASI (ERD):
--
--   users ──(wajib_pajak_id, nullable)──> wajib_pajak   (akun WAJIB_PAJAK ke datanya)
--   users ──1 : N──> pembayaran (audit: dicatat_oleh)
--   users ──(causer)──> activity_log                    (audit trail Spatie)
--   wajib_pajak ──1 : N──> kewajiban_pajak
--   kewajiban_pajak ──1 : N──> pembayaran
--   pembayaran ──1 : 1──> denda
--
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- users : akun login aplikasi (ADMIN / PETUGAS / WAJIB_PAJAK)
-- ---------------------------------------------------------------------
CREATE TABLE `users` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`             VARCHAR(255) NOT NULL,
  `username`         VARCHAR(255) NOT NULL,
  `email`            VARCHAR(255) NULL,
  `email_verified_at` TIMESTAMP NULL,
  `password`         VARCHAR(255) NOT NULL,             -- hash bcrypt
  `role`             ENUM('ADMIN','PETUGAS','WAJIB_PAJAK') NOT NULL DEFAULT 'PETUGAS',
  -- Penghubung akun login ke data wajib pajak miliknya.
  -- Diisi hanya untuk role WAJIB_PAJAK; NULL untuk ADMIN/PETUGAS.
  `wajib_pajak_id`   BIGINT UNSIGNED NULL,
  `remember_token`   VARCHAR(100) NULL,
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- wajib_pajak : data wajib pajak individu (NIK) / badan usaha (NPWP/NIB)
-- ---------------------------------------------------------------------
CREATE TABLE `wajib_pajak` (
  `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jenis`         ENUM('INDIVIDU','BADAN') NOT NULL,
  `nama`          VARCHAR(255) NOT NULL,
  `nik`           VARCHAR(16) NULL,                     -- individu (16 digit)
  `npwp`          VARCHAR(25) NULL,                     -- badan usaha
  `nib`           VARCHAR(30) NULL,                     -- badan usaha (alternatif)
  `email`         VARCHAR(255) NULL,
  `telepon`       VARCHAR(20) NULL,
  `alamat`        TEXT NULL,
  `status_aktif`  TINYINT(1) NOT NULL DEFAULT 1,
  `created_at`    TIMESTAMP NULL,
  `updated_at`    TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wajib_pajak_nik_unique` (`nik`),
  UNIQUE KEY `wajib_pajak_npwp_unique` (`npwp`),
  UNIQUE KEY `wajib_pajak_nib_unique` (`nib`),
  KEY `wajib_pajak_nama_index` (`nama`),
  KEY `wajib_pajak_jenis_index` (`jenis`),
  KEY `wajib_pajak_status_aktif_index` (`status_aktif`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- kewajiban_pajak : kewajiban pajak per wajib pajak
-- ---------------------------------------------------------------------
CREATE TABLE `kewajiban_pajak` (
  `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wajib_pajak_id`   BIGINT UNSIGNED NOT NULL,
  `jenis_pajak`      VARCHAR(255) NOT NULL,             -- mis. PBB, PPh, PPN
  `masa_pajak`       VARCHAR(255) NULL,                 -- mis. 2026-01
  `pokok_pajak`      DECIMAL(18,2) NOT NULL,
  `jatuh_tempo`      DATE NOT NULL,
  `status`           ENUM('BELUM_LUNAS','LUNAS','LEBIH_BAYAR') NOT NULL DEFAULT 'BELUM_LUNAS',
  `created_at`       TIMESTAMP NULL,
  `updated_at`       TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `kewajiban_pajak_jenis_pajak_index` (`jenis_pajak`),
  KEY `kewajiban_pajak_jatuh_tempo_index` (`jatuh_tempo`),
  KEY `kewajiban_pajak_status_index` (`status`),
  CONSTRAINT `kewajiban_pajak_wajib_pajak_id_foreign`
    FOREIGN KEY (`wajib_pajak_id`) REFERENCES `wajib_pajak` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- pembayaran : transaksi pembayaran pajak
-- ---------------------------------------------------------------------
CREATE TABLE `pembayaran` (
  `id`                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kewajiban_pajak_id`  BIGINT UNSIGNED NOT NULL,
  `nominal`             DECIMAL(18,2) NOT NULL,
  `tanggal_bayar`       DATE NOT NULL,
  `status`              ENUM('LUNAS','KURANG_BAYAR','LEBIH_BAYAR') NOT NULL,
  `keterangan`          TEXT NULL,
  `dicatat_oleh`        BIGINT UNSIGNED NULL,           -- audit: user pencatat
  `created_at`          TIMESTAMP NULL,
  `updated_at`          TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `pembayaran_tanggal_bayar_index` (`tanggal_bayar`),
  KEY `pembayaran_status_index` (`status`),
  CONSTRAINT `pembayaran_kewajiban_pajak_id_foreign`
    FOREIGN KEY (`kewajiban_pajak_id`) REFERENCES `kewajiban_pajak` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembayaran_dicatat_oleh_foreign`
    FOREIGN KEY (`dicatat_oleh`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- denda : denda yang dihitung otomatis saat pembayaran dicatat (1:1)
-- ---------------------------------------------------------------------
CREATE TABLE `denda` (
  `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pembayaran_id`   BIGINT UNSIGNED NOT NULL,
  `denda_telat`     DECIMAL(18,2) NOT NULL DEFAULT 0,   -- 2% x pokok pajak
  `denda_kurang`    DECIMAL(18,2) NOT NULL DEFAULT 0,   -- 1% x selisih kekurangan
  `total_denda`     DECIMAL(18,2) NOT NULL DEFAULT 0,
  `is_telat`        TINYINT(1) NOT NULL DEFAULT 0,
  `is_kurang_bayar` TINYINT(1) NOT NULL DEFAULT 0,
  `keterangan`      TEXT NULL,
  `created_at`      TIMESTAMP NULL,
  `updated_at`      TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `denda_pembayaran_id_foreign`
    FOREIGN KEY (`pembayaran_id`) REFERENCES `pembayaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- activity_log : jejak audit otomatis (Spatie Activity Log)
-- Mencatat setiap perubahan data (create/update/delete) + pelakunya.
-- ---------------------------------------------------------------------
CREATE TABLE `activity_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `log_name`     VARCHAR(255) NULL,
  `description`  TEXT NOT NULL,
  `subject_type` VARCHAR(255) NULL,                     -- model yang diubah
  `subject_id`   BIGINT UNSIGNED NULL,
  `event`        VARCHAR(255) NULL,                     -- created/updated/deleted
  `causer_type`  VARCHAR(255) NULL,                     -- user pelaku
  `causer_id`    BIGINT UNSIGNED NULL,
  `properties`   JSON NULL,                             -- perubahan atribut
  `batch_uuid`   CHAR(36) NULL,
  `created_at`   TIMESTAMP NULL,
  `updated_at`   TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `subject` (`subject_type`, `subject_id`),
  KEY `causer` (`causer_type`, `causer_id`),
  KEY `activity_log_log_name_index` (`log_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
