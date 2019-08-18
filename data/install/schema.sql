CREATE TABLE guest_token (
    id INT AUTO_INCREMENT NOT NULL,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    created DATETIME NOT NULL,
    confirmed TINYINT(1) NOT NULL,
    INDEX IDX_4AC9362FA76ED395 (user_id),
    INDEX guest_token_idx (token),
    PRIMARY KEY(id)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

# If module was uninstalled/reinstalled, reactivate the guests.
UPDATE user SET is_active = 1 WHERE role = "guest";
