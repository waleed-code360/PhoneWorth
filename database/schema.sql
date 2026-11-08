


CREATE DATABASE IF NOT EXISTS phoneworth
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE phoneworth;


CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS phone_models (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    brand_id INT UNSIGNED NOT NULL,
    model_name VARCHAR(150) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_model_per_brand (brand_id, model_name),
    CONSTRAINT fk_models_brand FOREIGN KEY (brand_id)
        REFERENCES brands(id) ON DELETE RESTRICT
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS phone_variants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id INT UNSIGNED NOT NULL,
    ram_gb SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    storage_gb SMALLINT UNSIGNED NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_variant (model_id, ram_gb, storage_gb),
    CONSTRAINT fk_variants_model FOREIGN KEY (model_id)
        REFERENCES phone_models(id) ON DELETE RESTRICT
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS countries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    country_name VARCHAR(100) NOT NULL,
    iso_code CHAR(2) NOT NULL UNIQUE,
    currency_code CHAR(3) NOT NULL,
    currency_symbol VARCHAR(10) NOT NULL,
    estimate_range_percent DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT chk_country_range CHECK (estimate_range_percent BETWEEN 0 AND 100)
) ENGINE=InnoDB;




CREATE TABLE IF NOT EXISTS market_prices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    variant_id INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    market_type VARCHAR(30) NOT NULL DEFAULT 'standard',
    base_price DECIMAL(12,2) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_market_price (variant_id, country_id, market_type),
    CONSTRAINT fk_market_variant FOREIGN KEY (variant_id)
        REFERENCES phone_variants(id) ON DELETE RESTRICT,
    CONSTRAINT fk_market_country FOREIGN KEY (country_id)
        REFERENCES countries(id) ON DELETE RESTRICT,
    CONSTRAINT chk_market_price CHECK (base_price > 0)
) ENGINE=InnoDB;


CREATE TABLE IF NOT EXISTS part_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_name VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS model_part_prices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id INT UNSIGNED NOT NULL,
    part_type_id INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    repair_cost DECIMAL(12,2) NULL,
    replacement_cost DECIMAL(12,2) NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_model_part_country (model_id, part_type_id, country_id),
    CONSTRAINT fk_part_price_model FOREIGN KEY (model_id)
        REFERENCES phone_models(id) ON DELETE RESTRICT,
    CONSTRAINT fk_part_price_part FOREIGN KEY (part_type_id)
        REFERENCES part_types(id) ON DELETE RESTRICT,
    CONSTRAINT fk_part_price_country FOREIGN KEY (country_id)
        REFERENCES countries(id) ON DELETE RESTRICT,
    CONSTRAINT chk_part_price_nonnegative CHECK (
        (repair_cost IS NULL OR repair_cost >= 0) AND
        (replacement_cost IS NULL OR replacement_cost >= 0)
    ),
    CONSTRAINT chk_part_price_available CHECK (
        repair_cost IS NOT NULL OR replacement_cost IS NOT NULL
    )
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS condition_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_text VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,
    damage_group VARCHAR(50) NOT NULL,
    country_id INT UNSIGNED NULL,
    brand_id INT UNSIGNED NULL,
    model_id INT UNSIGNED NULL,
    is_required TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_question_country FOREIGN KEY (country_id)
        REFERENCES countries(id) ON DELETE RESTRICT,
    CONSTRAINT fk_question_brand FOREIGN KEY (brand_id)
        REFERENCES brands(id) ON DELETE RESTRICT,
    CONSTRAINT fk_question_model FOREIGN KEY (model_id)
        REFERENCES phone_models(id) ON DELETE RESTRICT
) ENGINE=InnoDB;







CREATE TABLE IF NOT EXISTS condition_options (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    option_text VARCHAR(150) NOT NULL,
    rule_type ENUM('none', 'percentage', 'part_cost') NOT NULL DEFAULT 'none',
    percentage_value DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    part_cost_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    part_type_id INT UNSIGNED NULL,
    cost_kind ENUM('repair', 'replacement') NULL,
    fallback_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    score_penalty TINYINT UNSIGNED NOT NULL DEFAULT 0,
    needs_note TINYINT(1) NOT NULL DEFAULT 0,
    blocks_valuation TINYINT(1) NOT NULL DEFAULT 0,
    market_type VARCHAR(30) NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_option_per_question (question_id, option_text),
    CONSTRAINT fk_option_question FOREIGN KEY (question_id)
        REFERENCES condition_questions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_option_part FOREIGN KEY (part_type_id)
        REFERENCES part_types(id) ON DELETE RESTRICT,
    CONSTRAINT chk_option_percent CHECK (
        percentage_value BETWEEN 0 AND 100 AND
        part_cost_percent BETWEEN 0 AND 100 AND
        fallback_percent BETWEEN 0 AND 100 AND
        score_penalty <= 100
    ),
    CONSTRAINT chk_part_rule_fields CHECK (
        rule_type <> 'part_cost' OR (part_type_id IS NOT NULL AND cost_kind IS NOT NULL)
    )
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS valuations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    variant_id INT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NOT NULL,
    market_type VARCHAR(30) NOT NULL DEFAULT 'standard',
    currency_code CHAR(3) NOT NULL,
    base_price DECIMAL(12,2) NOT NULL,
    total_deduction DECIMAL(12,2) NOT NULL,
    estimated_price DECIMAL(12,2) NOT NULL,
    minimum_price DECIMAL(12,2) NOT NULL,
    maximum_price DECIMAL(12,2) NOT NULL,
    condition_score TINYINT UNSIGNED NOT NULL,
    used_fallback TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_valuation_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_valuation_variant FOREIGN KEY (variant_id)
        REFERENCES phone_variants(id) ON DELETE RESTRICT,
    CONSTRAINT fk_valuation_country FOREIGN KEY (country_id)
        REFERENCES countries(id) ON DELETE RESTRICT,
    CONSTRAINT chk_valuation_amounts CHECK (
        base_price >= 0 AND total_deduction >= 0 AND estimated_price >= 0 AND
        minimum_price >= 0 AND maximum_price >= minimum_price AND
        estimated_price BETWEEN minimum_price AND maximum_price AND
        condition_score <= 100
    )
) ENGINE=InnoDB;



CREATE TABLE IF NOT EXISTS valuation_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    valuation_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NOT NULL,
    question_text_snapshot VARCHAR(255) NOT NULL,
    option_text_snapshot VARCHAR(150) NOT NULL,
    
    
    user_note VARCHAR(500) NULL,
    applied_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    score_penalty_applied TINYINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_valuation_question (valuation_id, question_id),
    CONSTRAINT fk_answer_valuation FOREIGN KEY (valuation_id)
        REFERENCES valuations(id) ON DELETE RESTRICT,
    CONSTRAINT fk_answer_question FOREIGN KEY (question_id)
        REFERENCES condition_questions(id) ON DELETE RESTRICT,
    CONSTRAINT fk_answer_option FOREIGN KEY (option_id)
        REFERENCES condition_options(id) ON DELETE RESTRICT,
    CONSTRAINT chk_answer_deduction CHECK (
        applied_deduction >= 0 AND score_penalty_applied <= 100
    )
) ENGINE=InnoDB;



