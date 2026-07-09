
CREATE DATABASE IF NOT EXISTS purble_pairs;

USE purble_pairs;

CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    is_admin   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS leaderboard (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    difficulty VARCHAR(20)  NOT NULL,
    moves      INT          NOT NULL,
    time       INT          NOT NULL,  -- seconds taken to complete the game
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS endless_leaderboard (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    user_id            INT          NOT NULL,
    score              INT          NOT NULL,
    reached_difficulty VARCHAR(20)  NOT NULL,
    created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

INSERT INTO users (username, password, is_admin)
VALUES ('admin', '2D31ZFNG', 1)
ON DUPLICATE KEY UPDATE is_admin = 1;
