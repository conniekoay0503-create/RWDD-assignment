-- =====================================================================
-- E-Waste & Repair Cafe - database for XAMPP (MariaDB / MySQL)
-- Group 52, AAPP012-4-2-RWDD
-- Import: phpMyAdmin > Import > choose this file > Go
-- Demo password for every account: Password123
-- =====================================================================

CREATE DATABASE IF NOT EXISTS ewaste_repair_cafe
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE ewaste_repair_cafe;

-- Drop old tables so the file can be imported again (children first)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS device_status_history, feedback, points_transactions, devices,
  redemptions, volunteer_request, announcements, notifications, rewards, challenges,
  categories, officers, volunteers, students, admins, users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. USERS : common account details of every user
-- ---------------------------------------------------------------------
CREATE TABLE users (
  tp_number   VARCHAR(10)  NOT NULL,
  name        VARCHAR(100) NOT NULL,
  email       VARCHAR(100) NOT NULL,
  password    VARCHAR(255) NOT NULL,
  phone       VARCHAR(15)  NOT NULL,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tp_number),
  UNIQUE KEY uk_users_email (email)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2-5. Role (subtype) tables : each user is in exactly ONE of them
-- ---------------------------------------------------------------------
CREATE TABLE admins (
  tp_number    VARCHAR(10) NOT NULL,
  assigned_by  VARCHAR(10) NULL,              -- NULL only for the first admin
  assigned_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tp_number),
  CONSTRAINT fk_admins_user FOREIGN KEY (tp_number)   REFERENCES users(tp_number),
  CONSTRAINT fk_admins_by   FOREIGN KEY (assigned_by) REFERENCES admins(tp_number)
) ENGINE=InnoDB;

CREATE TABLE students (
  tp_number  VARCHAR(10) NOT NULL,
  PRIMARY KEY (tp_number),
  CONSTRAINT fk_students_user FOREIGN KEY (tp_number) REFERENCES users(tp_number)
) ENGINE=InnoDB;

CREATE TABLE volunteers (
  tp_number    VARCHAR(10) NOT NULL,
  assigned_by  VARCHAR(10) NOT NULL,
  assigned_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tp_number),
  CONSTRAINT fk_volunteers_user FOREIGN KEY (tp_number)   REFERENCES users(tp_number),
  CONSTRAINT fk_volunteers_by   FOREIGN KEY (assigned_by) REFERENCES admins(tp_number)
) ENGINE=InnoDB;

CREATE TABLE officers (
  tp_number    VARCHAR(10) NOT NULL,
  assigned_by  VARCHAR(10) NOT NULL,
  assigned_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (tp_number),
  CONSTRAINT fk_officers_user FOREIGN KEY (tp_number)   REFERENCES users(tp_number),
  CONSTRAINT fk_officers_by   FOREIGN KEY (assigned_by) REFERENCES admins(tp_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. CATEGORIES
-- ---------------------------------------------------------------------
CREATE TABLE categories (
  categoryID     VARCHAR(10)    NOT NULL,
  category_name  VARCHAR(50)  NOT NULL,
  multiplier     DECIMAL(7,2) NOT NULL,
  max_points     INT(11)      NOT NULL,
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (categoryID),
  UNIQUE KEY uk_category_name (category_name),
  CONSTRAINT chk_cat_multiplier CHECK (multiplier > 0),
  CONSTRAINT chk_cat_max        CHECK (max_points > 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 7. CHALLENGES
-- ---------------------------------------------------------------------
CREATE TABLE challenges (
  challengeID   VARCHAR(10)    NOT NULL,
  created_by    VARCHAR(10)  NOT NULL,
  title         VARCHAR(100) NOT NULL,
  description   VARCHAR(255) NOT NULL,
  target_kg     DECIMAL(8,2) NOT NULL,
  start_date    DATE         NOT NULL,
  end_date      DATE         NOT NULL,
  bonus_points  INT(11)      NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (challengeID),
  CONSTRAINT fk_challenges_admin FOREIGN KEY (created_by) REFERENCES admins(tp_number),
  CONSTRAINT chk_ch_target CHECK (target_kg > 0),
  CONSTRAINT chk_ch_bonus  CHECK (bonus_points > 0),
  CONSTRAINT chk_ch_dates  CHECK (end_date >= start_date)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 8. REWARDS (cashback)
-- ---------------------------------------------------------------------
CREATE TABLE rewards (
  rewardID         VARCHAR(10)    NOT NULL,
  reward_name      VARCHAR(100) NOT NULL,
  description      VARCHAR(255) NOT NULL,
  points_required  INT(11)      NOT NULL,
  cash_value       DECIMAL(8,2) NOT NULL,
  stock            INT(11)      NOT NULL DEFAULT 0,
  PRIMARY KEY (rewardID),
  CONSTRAINT chk_rw_points CHECK (points_required > 0),
  CONSTRAINT chk_rw_cash   CHECK (cash_value > 0),
  CONSTRAINT chk_rw_stock  CHECK (stock >= 0)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. NOTIFICATIONS (personal) and 10. ANNOUNCEMENTS (everyone)
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  notificationID  VARCHAR(10)    NOT NULL,
  tp_number       VARCHAR(10)  NOT NULL,          -- recipient
  created_by      VARCHAR(10)  NULL,              -- NULL = system-generated
  title           VARCHAR(100) NOT NULL,
  message         VARCHAR(255) NOT NULL,
  is_read         TINYINT(1)   NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (notificationID),
  CONSTRAINT fk_notif_user  FOREIGN KEY (tp_number)  REFERENCES users(tp_number),
  CONSTRAINT fk_notif_admin FOREIGN KEY (created_by) REFERENCES admins(tp_number)
) ENGINE=InnoDB;

CREATE TABLE announcements (
  announcementID  VARCHAR(10)    NOT NULL,
  created_by      VARCHAR(10)  NOT NULL,
  title           VARCHAR(100) NOT NULL,
  message         VARCHAR(500) NOT NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcementID),
  CONSTRAINT fk_announce_admin FOREIGN KEY (created_by) REFERENCES admins(tp_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 11. VOLUNTEER_REQUEST
-- ---------------------------------------------------------------------
CREATE TABLE volunteer_request (
  requestID     VARCHAR(10)    NOT NULL,
  tp_number     VARCHAR(10)  NOT NULL,
  reviewed_by   VARCHAR(10)  NULL,
  reason        VARCHAR(255) NOT NULL,
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  requested_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at   DATETIME     NULL,
  PRIMARY KEY (requestID),
  CONSTRAINT fk_vr_user  FOREIGN KEY (tp_number)   REFERENCES users(tp_number),
  CONSTRAINT fk_vr_admin FOREIGN KEY (reviewed_by) REFERENCES admins(tp_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 12. REDEMPTIONS (points_used and cash_value are snapshots)
-- ---------------------------------------------------------------------
CREATE TABLE redemptions (
  redemptionID  VARCHAR(10)    NOT NULL,
  tp_number     VARCHAR(10)  NOT NULL,
  rewardID      VARCHAR(10)    NOT NULL,
  reviewed_by   VARCHAR(10)  NULL,
  points_used   INT(11)      NOT NULL,
  cash_value    DECIMAL(8,2) NOT NULL,
  status        ENUM('pending','completed','cancelled') NOT NULL DEFAULT 'pending',
  requested_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reviewed_at   DATETIME     NULL,
  PRIMARY KEY (redemptionID),
  CONSTRAINT fk_red_user   FOREIGN KEY (tp_number)   REFERENCES users(tp_number),
  CONSTRAINT fk_red_reward FOREIGN KEY (rewardID)    REFERENCES rewards(rewardID),
  CONSTRAINT fk_red_admin  FOREIGN KEY (reviewed_by) REFERENCES admins(tp_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 13. DEVICES
-- ---------------------------------------------------------------------
CREATE TABLE devices (
  deviceID           VARCHAR(10)    NOT NULL,
  submitted_by       VARCHAR(10)  NOT NULL,
  categoryID         VARCHAR(10)    NOT NULL,
  claimed_by         VARCHAR(10)  NULL,
  processed_by       VARCHAR(10)  NULL,
  device_name        VARCHAR(100) NOT NULL,
  fault_description  VARCHAR(255) NOT NULL,
  photo_path         VARCHAR(255) NOT NULL,
  weight_kg          DECIMAL(6,2) NULL,
  status             ENUM('Submitted','Cancelled','Dropped Off','Claimed','In Repair','Ready for Collection','Completed','Pending Consent','Awaiting Recycle','Recycled','Returned Unrepaired','Collected Unrepaired','Disposed') NOT NULL DEFAULT 'Submitted',
  disposal_method    ENUM('Certified e-waste recycler','Battery take-back programme','Metal recovery','Harvest parts for repair stock') NULL,
  terms_accepted     TINYINT(1)   NOT NULL,
  recycle_consent    TINYINT(1)   NULL,
  submitted_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status_changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (deviceID),
  CONSTRAINT fk_dev_user      FOREIGN KEY (submitted_by) REFERENCES users(tp_number),
  CONSTRAINT fk_dev_category  FOREIGN KEY (categoryID)   REFERENCES categories(categoryID),
  CONSTRAINT fk_dev_volunteer FOREIGN KEY (claimed_by)   REFERENCES volunteers(tp_number),
  CONSTRAINT fk_dev_officer   FOREIGN KEY (processed_by) REFERENCES officers(tp_number),
  CONSTRAINT chk_dev_weight   CHECK (weight_kg IS NULL OR (weight_kg > 0 AND weight_kg <= 50)),
  CONSTRAINT chk_dev_terms    CHECK (terms_accepted = 1)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 14. POINTS_TRANSACTIONS (balance = SUM(points), never stored)
-- ---------------------------------------------------------------------
CREATE TABLE points_transactions (
  transactionID  VARCHAR(10)    NOT NULL,
  tp_number      VARCHAR(10)  NOT NULL,
  deviceID       VARCHAR(10)    NULL,
  redemptionID   VARCHAR(10)    NULL,
  challengeID    VARCHAR(10)    NULL,
  points         INT(11)      NOT NULL,
  txn_type       ENUM('earn','redeem','bonus') NOT NULL,
  description    VARCHAR(255) NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (transactionID),
  UNIQUE KEY uk_pt_device_user    (deviceID, tp_number),     -- earn once per device per user
  UNIQUE KEY uk_pt_challenge_user (challengeID, tp_number),  -- one bonus per challenge per user
  UNIQUE KEY uk_pt_redemption     (redemptionID),
  CONSTRAINT fk_pt_user       FOREIGN KEY (tp_number)    REFERENCES users(tp_number),
  CONSTRAINT fk_pt_device     FOREIGN KEY (deviceID)     REFERENCES devices(deviceID),
  CONSTRAINT fk_pt_redemption FOREIGN KEY (redemptionID) REFERENCES redemptions(redemptionID),
  CONSTRAINT fk_pt_challenge  FOREIGN KEY (challengeID)  REFERENCES challenges(challengeID)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 15. FEEDBACK
-- ---------------------------------------------------------------------
CREATE TABLE feedback (
  feedbackID    VARCHAR(10)    NOT NULL,
  tp_number     VARCHAR(10)  NOT NULL,
  deviceID      VARCHAR(10)    NOT NULL,
  replied_by    VARCHAR(10)  NULL,
  message       VARCHAR(255) NOT NULL,
  admin_reply   VARCHAR(255) NULL,
  submitted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  replied_at    DATETIME     NULL,
  PRIMARY KEY (feedbackID),
  CONSTRAINT fk_fb_user   FOREIGN KEY (tp_number)  REFERENCES users(tp_number),
  CONSTRAINT fk_fb_device FOREIGN KEY (deviceID)   REFERENCES devices(deviceID),
  CONSTRAINT fk_fb_admin  FOREIGN KEY (replied_by) REFERENCES admins(tp_number)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 16. DEVICE_STATUS_HISTORY
-- ---------------------------------------------------------------------
CREATE TABLE device_status_history (
  historyID   VARCHAR(10)    NOT NULL,
  deviceID    VARCHAR(10)    NOT NULL,
  changed_by  VARCHAR(10)  NULL,
  new_status  ENUM('Submitted','Cancelled','Dropped Off','Claimed','In Repair','Ready for Collection','Completed','Pending Consent','Awaiting Recycle','Recycled','Returned Unrepaired','Collected Unrepaired','Disposed') NOT NULL,
  remarks     VARCHAR(255) NULL,
  changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (historyID),
  CONSTRAINT fk_dsh_device FOREIGN KEY (deviceID)   REFERENCES devices(deviceID),
  CONSTRAINT fk_dsh_user   FOREIGN KEY (changed_by) REFERENCES users(tp_number)
) ENGINE=InnoDB;

-- =====================================================================
-- DEMO DATA (dates are relative to the day you import)
-- =====================================================================

INSERT INTO users (tp_number, name, email, password, phone, is_active, created_at) VALUES
('TP090995', 'Koay Shin Shee', 'koay@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0123456789', 1, NOW() - INTERVAL 2880 HOUR),
('TP100002', 'Nur Aisyah Rahman', 'aisyah@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0132223344', 1, NOW() - INTERVAL 2400 HOUR),
('TP087766', 'Jerald Long Jitson', 'jerald@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0145556677', 1, NOW() - INTERVAL 2640 HOUR),
('TP091586', 'Lim Wen Ying', 'lim@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0167778899', 1, NOW() - INTERVAL 2160 HOUR),
('TP087791', 'Genieve Ki Yi Nee', 'genieve@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0171112233', 1, NOW() - INTERVAL 1920 HOUR),
('TP095001', 'Daniel Wong', 'daniel@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0182223344', 1, NOW() - INTERVAL 1800 HOUR),
('TP095002', 'Priya Suresh', 'priya@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0193334455', 1, NOW() - INTERVAL 1680 HOUR),
('TP095003', 'Mei Ling Tan', 'meiling@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0114445566', 1, NOW() - INTERVAL 1680 HOUR),
('TP095004', 'Arjun Nair', 'arjun@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0125556677', 1, NOW() - INTERVAL 1440 HOUR),
('TP095005', 'Ken Ho', 'ken@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0136667788', 0, NOW() - INTERVAL 1200 HOUR),
('TP095006', 'Siti Aminah', 'siti@mail.apu.edu.my', '$2y$10$qFedhZJVGqmitB7qQKyS7e1TK38b7jnd90f55MrqRmC1uslChRQ3S', '0147778899', 1, NOW() - INTERVAL 720 HOUR);

INSERT INTO admins (tp_number, assigned_by, assigned_at) VALUES
('TP090995', NULL, NOW() - INTERVAL 2880 HOUR),
('TP100002', 'TP090995', NOW() - INTERVAL 2400 HOUR);

INSERT INTO students (tp_number) VALUES ('TP087791'), ('TP095001'), ('TP095002'), ('TP095003'), ('TP095004'), ('TP095005'), ('TP095006');

INSERT INTO volunteers (tp_number, assigned_by, assigned_at) VALUES ('TP091586', 'TP090995', NOW() - INTERVAL 2112 HOUR);

INSERT INTO officers (tp_number, assigned_by, assigned_at) VALUES ('TP087766', 'TP090995', NOW() - INTERVAL 2592 HOUR);

INSERT INTO categories (categoryID, category_name, multiplier, max_points, is_active) VALUES
('C001', 'Battery', 1000.00, 100, 1),
('C002', 'Phone / Tablet', 500.00, 300, 1),
('C003', 'Laptop / Desktop', 300.00, 500, 1),
('C004', 'Cable / Accessory', 100.00, 50, 1),
('C005', 'Printer / Scanner', 200.00, 200, 0);

INSERT INTO rewards (rewardID, reward_name, description, points_required, cash_value, stock) VALUES
('RW001', 'RM2 Cashback', 'Paid to the APCard linked to your email', 100, 2.00, 20),
('RW002', 'RM3 Cashback', 'Paid to the APCard linked to your email', 150, 3.00, 14),
('RW003', 'RM5 Cashback', 'Paid to the APCard linked to your email', 240, 5.00, 10),
('RW004', 'RM10 Cashback', 'Paid to the APCard linked to your email', 460, 10.00, 5),
('RW005', 'RM20 Cashback', 'Paid to the APCard linked to your email', 880, 20.00, 2);

INSERT INTO challenges (challengeID, created_by, title, description, target_kg, start_date, end_date, bonus_points, created_at) VALUES
('CH001', 'TP090995', 'Battery Round-Up', 'Recycle or repair 0.5 kg of e-waste during the challenge', 0.50, CURDATE() - INTERVAL 50 DAY, CURDATE() - INTERVAL 21 DAY, 30, NOW() - INTERVAL 1320 HOUR),
('CH002', 'TP090995', 'Month of Repair', 'Get 5 kg of your devices repaired or recycled this month', 5.00, CURDATE() - INTERVAL 10 DAY, CURDATE() + INTERVAL 20 DAY, 50, NOW() - INTERVAL 288 HOUR),
('CH003', 'TP100002', 'Final Week Cable Drive', 'Bring in old cables and accessories', 1.00, CURDATE() + INTERVAL 30 DAY, CURDATE() + INTERVAL 37 DAY, 40, NOW() - INTERVAL 24 HOUR);

INSERT INTO announcements (announcementID, created_by, title, message, created_at) VALUES
('A001', 'TP090995', 'Welcome to the E-Waste & Repair Cafe', 'Drop your broken gadgets at the Green Hub, Block E. Volunteers repair what they can and recycle the rest properly.', NOW() - INTERVAL 1440 HOUR),
('A002', 'TP090995', 'New challenge: Month of Repair', 'Get 5 kg of your devices repaired or recycled this month to earn 50 bonus points.', NOW() - INTERVAL 288 HOUR);

INSERT INTO volunteer_request (requestID, tp_number, reviewed_by, reason, status, requested_at, reviewed_at) VALUES
('VR001', 'TP091586', 'TP090995', 'I have fixed phones for my family for years', 'approved', NOW() - INTERVAL 2136 HOUR, NOW() - INTERVAL 2112 HOUR),
('VR002', 'TP095004', 'TP100002', 'Want volunteer hours', 'rejected', NOW() - INTERVAL 480 HOUR, NOW() - INTERVAL 456 HOUR),
('VR003', 'TP095006', NULL, 'I am studying electronics and want hands-on repair practice', 'pending', NOW() - INTERVAL 48 HOUR, NULL);

INSERT INTO devices (deviceID, submitted_by, categoryID, claimed_by, processed_by, device_name, fault_description, photo_path, weight_kg, status, disposal_method, terms_accepted, recycle_consent, submitted_at, status_changed_at) VALUES
('D001', 'TP087791', 'C002', 'TP091586', NULL, 'Pixel 6', 'Battery drains within 2 hours', 'uploads/devices/demo_1.jpg', 0.21, 'In Repair', NULL, 1, NULL, NOW() - INTERVAL 216 HOUR, NOW() - INTERVAL 96 HOUR),
('D002', 'TP087791', 'C003', 'TP091586', NULL, 'ThinkPad T480', 'Screen does not turn on', 'uploads/devices/demo_2.jpg', 2.10, 'Pending Consent', NULL, 1, NULL, NOW() - INTERVAL 288 HOUR, NOW() - INTERVAL 48 HOUR),
('D003', 'TP087791', 'C004', NULL, NULL, 'USB-C charging cable', 'Only charges at one angle', 'uploads/devices/demo_3.jpg', NULL, 'Submitted', NULL, 1, NULL, NOW() - INTERVAL 24 HOUR, NOW() - INTERVAL 24 HOUR),
('D004', 'TP095003', 'C002', 'TP091586', 'TP087766', 'Galaxy Tab A', 'Cracked screen and swollen battery', 'uploads/devices/demo_4.jpg', 0.48, 'Recycled', 'Certified e-waste recycler', 1, 1, NOW() - INTERVAL 1080 HOUR, NOW() - INTERVAL 864 HOUR),
('D005', 'TP095003', 'C001', 'TP091586', 'TP087766', 'Power bank cell', 'Does not hold charge', 'uploads/devices/demo_5.jpg', 0.05, 'Recycled', 'Battery take-back programme', 1, 1, NOW() - INTERVAL 960 HOUR, NOW() - INTERVAL 792 HOUR),
('D006', 'TP095001', 'C001', 'TP091586', NULL, 'Laptop battery pack', 'Battery is swollen', 'uploads/devices/demo_6.jpg', 0.32, 'Awaiting Recycle', NULL, 1, 1, NOW() - INTERVAL 192 HOUR, NOW() - INTERVAL 96 HOUR),
('D007', 'TP095002', 'C002', 'TP091586', NULL, 'Nokia 3310 (2017)', 'Keypad not responding', 'uploads/devices/demo_7.jpg', 0.13, 'Awaiting Recycle', NULL, 1, 1, NOW() - INTERVAL 168 HOUR, NOW() - INTERVAL 72 HOUR),
('D008', 'TP095001', 'C002', 'TP091586', NULL, 'Redmi Note 11', 'Charging port loose', 'uploads/devices/demo_8.jpg', 0.20, 'Completed', NULL, 1, NULL, NOW() - INTERVAL 720 HOUR, NOW() - INTERVAL 576 HOUR),
('D009', 'TP095004', 'C003', NULL, NULL, 'Dell Inspiron 15', 'Coffee spill, keyboard row stopped working', 'uploads/devices/demo_9.jpg', NULL, 'Dropped Off', NULL, 1, NULL, NOW() - INTERVAL 96 HOUR, NOW() - INTERVAL 72 HOUR),
('D010', 'TP095002', 'C002', 'TP091586', NULL, 'iPad Air 2', 'Will not charge', 'uploads/devices/demo_10.jpg', 0.44, 'Ready for Collection', NULL, 1, NULL, NOW() - INTERVAL 528 HOUR, NOW() - INTERVAL 240 HOUR),
('D011', 'TP095004', 'C004', 'TP091586', NULL, 'Logitech wireless mouse', 'Left click double-clicks', 'uploads/devices/demo_11.jpg', 0.09, 'Returned Unrepaired', NULL, 1, 0, NOW() - INTERVAL 360 HOUR, NOW() - INTERVAL 72 HOUR),
('D012', 'TP095006', 'C001', NULL, NULL, 'Xiaomi power bank', 'Only charges to 45%', 'uploads/devices/demo_12.jpg', NULL, 'Dropped Off', NULL, 1, NULL, NOW() - INTERVAL 72 HOUR, NOW() - INTERVAL 48 HOUR),
('D013', 'TP095001', 'C004', NULL, NULL, 'Old phone charger', 'Frayed cable', 'uploads/devices/demo_13.jpg', NULL, 'Cancelled', NULL, 1, NULL, NOW() - INTERVAL 480 HOUR, NOW() - INTERVAL 456 HOUR),
('D014', 'TP095003', 'C002', 'TP091586', NULL, 'Kindle Paperwhite', 'Screen frozen', 'uploads/devices/demo_14.jpg', 0.18, 'Collected Unrepaired', NULL, 1, 0, NOW() - INTERVAL 672 HOUR, NOW() - INTERVAL 456 HOUR),
('D015', 'TP095002', 'C004', 'TP091586', 'TP087766', 'Wired earbuds', 'Left side silent', 'uploads/devices/demo_15.jpg', 0.03, 'Disposed', 'Certified e-waste recycler', 1, NULL, NOW() - INTERVAL 840 HOUR, NOW() - INTERVAL 480 HOUR);

INSERT INTO device_status_history (historyID, deviceID, changed_by, new_status, remarks, changed_at) VALUES
('H001', 'D001', 'TP087791', 'Submitted', NULL, NOW() - INTERVAL 216 HOUR),
('H002', 'D001', 'TP087791', 'Dropped Off', NULL, NOW() - INTERVAL 192 HOUR),
('H003', 'D001', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 168 HOUR),
('H004', 'D001', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 144 HOUR),
('H005', 'D001', 'TP091586', 'In Repair', 'Ordered a replacement battery', NOW() - INTERVAL 96 HOUR),
('H006', 'D002', 'TP087791', 'Submitted', NULL, NOW() - INTERVAL 288 HOUR),
('H007', 'D002', 'TP087791', 'Dropped Off', NULL, NOW() - INTERVAL 264 HOUR),
('H008', 'D002', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 240 HOUR),
('H009', 'D002', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 216 HOUR),
('H010', 'D002', 'TP091586', 'Pending Consent', 'Unrepairable - motherboard damaged', NOW() - INTERVAL 48 HOUR),
('H011', 'D003', 'TP087791', 'Submitted', NULL, NOW() - INTERVAL 24 HOUR),
('H012', 'D004', 'TP095003', 'Submitted', NULL, NOW() - INTERVAL 1080 HOUR),
('H013', 'D004', 'TP095003', 'Dropped Off', NULL, NOW() - INTERVAL 1056 HOUR),
('H014', 'D004', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 1032 HOUR),
('H015', 'D004', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 1008 HOUR),
('H016', 'D004', 'TP091586', 'Pending Consent', 'Unrepairable - battery unsafe', NOW() - INTERVAL 960 HOUR),
('H017', 'D004', 'TP095003', 'Awaiting Recycle', NULL, NOW() - INTERVAL 936 HOUR),
('H018', 'D004', 'TP087766', 'Recycled', 'Certified e-waste recycler', NOW() - INTERVAL 864 HOUR),
('H019', 'D005', 'TP095003', 'Submitted', NULL, NOW() - INTERVAL 960 HOUR),
('H020', 'D005', 'TP095003', 'Dropped Off', NULL, NOW() - INTERVAL 936 HOUR),
('H021', 'D005', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 912 HOUR),
('H022', 'D005', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 888 HOUR),
('H023', 'D005', 'TP091586', 'Pending Consent', 'Cell is dead', NOW() - INTERVAL 864 HOUR),
('H024', 'D005', 'TP095003', 'Awaiting Recycle', NULL, NOW() - INTERVAL 840 HOUR),
('H025', 'D005', 'TP087766', 'Recycled', 'Battery take-back programme', NOW() - INTERVAL 792 HOUR),
('H026', 'D006', 'TP095001', 'Submitted', NULL, NOW() - INTERVAL 192 HOUR),
('H027', 'D006', 'TP095001', 'Dropped Off', NULL, NOW() - INTERVAL 168 HOUR),
('H028', 'D006', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 144 HOUR),
('H029', 'D006', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 144 HOUR),
('H030', 'D006', 'TP091586', 'Pending Consent', 'Unsafe to repair', NOW() - INTERVAL 120 HOUR),
('H031', 'D006', 'TP095001', 'Awaiting Recycle', NULL, NOW() - INTERVAL 96 HOUR),
('H032', 'D007', 'TP095002', 'Submitted', NULL, NOW() - INTERVAL 168 HOUR),
('H033', 'D007', 'TP095002', 'Dropped Off', NULL, NOW() - INTERVAL 144 HOUR),
('H034', 'D007', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 120 HOUR),
('H035', 'D007', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 120 HOUR),
('H036', 'D007', 'TP091586', 'Pending Consent', 'Keypad board corroded', NOW() - INTERVAL 96 HOUR),
('H037', 'D007', 'TP095002', 'Awaiting Recycle', NULL, NOW() - INTERVAL 72 HOUR),
('H038', 'D008', 'TP095001', 'Submitted', NULL, NOW() - INTERVAL 720 HOUR),
('H039', 'D008', 'TP095001', 'Dropped Off', NULL, NOW() - INTERVAL 696 HOUR),
('H040', 'D008', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 672 HOUR),
('H041', 'D008', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 648 HOUR),
('H042', 'D008', 'TP091586', 'Ready for Collection', 'Replaced the charging port', NOW() - INTERVAL 600 HOUR),
('H043', 'D008', 'TP095001', 'Completed', NULL, NOW() - INTERVAL 576 HOUR),
('H044', 'D009', 'TP095004', 'Submitted', NULL, NOW() - INTERVAL 96 HOUR),
('H045', 'D009', 'TP095004', 'Dropped Off', NULL, NOW() - INTERVAL 72 HOUR),
('H046', 'D010', 'TP095002', 'Submitted', NULL, NOW() - INTERVAL 528 HOUR),
('H047', 'D010', 'TP095002', 'Dropped Off', NULL, NOW() - INTERVAL 504 HOUR),
('H048', 'D010', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 480 HOUR),
('H049', 'D010', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 456 HOUR),
('H050', 'D010', 'TP091586', 'Ready for Collection', 'Cleaned the charging port', NOW() - INTERVAL 240 HOUR),
('H051', 'D011', 'TP095004', 'Submitted', NULL, NOW() - INTERVAL 360 HOUR),
('H052', 'D011', 'TP095004', 'Dropped Off', NULL, NOW() - INTERVAL 336 HOUR),
('H053', 'D011', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 312 HOUR),
('H054', 'D011', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 288 HOUR),
('H055', 'D011', 'TP091586', 'Pending Consent', 'Switch cannot be replaced', NOW() - INTERVAL 144 HOUR),
('H056', 'D011', 'TP095004', 'Returned Unrepaired', NULL, NOW() - INTERVAL 72 HOUR),
('H057', 'D012', 'TP095006', 'Submitted', NULL, NOW() - INTERVAL 72 HOUR),
('H058', 'D012', 'TP095006', 'Dropped Off', NULL, NOW() - INTERVAL 48 HOUR),
('H059', 'D013', 'TP095001', 'Submitted', NULL, NOW() - INTERVAL 480 HOUR),
('H060', 'D013', 'TP095001', 'Cancelled', NULL, NOW() - INTERVAL 456 HOUR),
('H061', 'D014', 'TP095003', 'Submitted', NULL, NOW() - INTERVAL 672 HOUR),
('H062', 'D014', 'TP095003', 'Dropped Off', NULL, NOW() - INTERVAL 648 HOUR),
('H063', 'D014', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 624 HOUR),
('H064', 'D014', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 600 HOUR),
('H065', 'D014', 'TP091586', 'Pending Consent', 'Display panel broken', NOW() - INTERVAL 528 HOUR),
('H066', 'D014', 'TP095003', 'Returned Unrepaired', NULL, NOW() - INTERVAL 504 HOUR),
('H067', 'D014', 'TP095003', 'Collected Unrepaired', NULL, NOW() - INTERVAL 456 HOUR),
('H068', 'D015', 'TP095002', 'Submitted', NULL, NOW() - INTERVAL 840 HOUR),
('H069', 'D015', 'TP095002', 'Dropped Off', NULL, NOW() - INTERVAL 816 HOUR),
('H070', 'D015', 'TP091586', 'Claimed', NULL, NOW() - INTERVAL 792 HOUR),
('H071', 'D015', 'TP091586', 'In Repair', 'Received, weight recorded', NOW() - INTERVAL 768 HOUR),
('H072', 'D015', 'TP091586', 'Pending Consent', 'Wire broken inside the jack', NOW() - INTERVAL 720 HOUR),
('H073', 'D015', 'TP087766', 'Disposed', 'Not collected within 7 days', NOW() - INTERVAL 480 HOUR);

INSERT INTO redemptions (redemptionID, tp_number, rewardID, reviewed_by, points_used, cash_value, status, requested_at, reviewed_at) VALUES
('R001', 'TP095003', 'RW002', 'TP090995', 150, 3.00, 'completed', NOW() - INTERVAL 720 HOUR, NOW() - INTERVAL 696 HOUR),
('R002', 'TP095001', 'RW001', NULL, 100, 2.00, 'pending', NOW() - INTERVAL 24 HOUR, NULL);

INSERT INTO points_transactions (transactionID, tp_number, deviceID, redemptionID, challengeID, points, txn_type, description, created_at) VALUES
('T001', 'TP095003', 'D004', NULL, NULL, 240, 'earn', 'Galaxy Tab A recycled', NOW() - INTERVAL 864 HOUR),
('T002', 'TP095003', 'D005', NULL, NULL, 50, 'earn', 'Power bank cell recycled', NOW() - INTERVAL 792 HOUR),
('T003', 'TP095003', NULL, NULL, 'CH001', 30, 'bonus', 'Challenge bonus: Battery Round-Up', NOW() - INTERVAL 792 HOUR),
('T004', 'TP095003', NULL, 'R001', NULL, -150, 'redeem', 'Redeemed RM3 Cashback', NOW() - INTERVAL 720 HOUR),
('T005', 'TP095001', 'D008', NULL, NULL, 200, 'earn', 'Redmi Note 11 repaired', NOW() - INTERVAL 576 HOUR),
('T006', 'TP091586', 'D008', NULL, NULL, 100, 'earn', 'Repaired Redmi Note 11', NOW() - INTERVAL 576 HOUR),
('T007', 'TP095001', NULL, 'R002', NULL, -100, 'redeem', 'Redeemed RM2 Cashback', NOW() - INTERVAL 24 HOUR);

INSERT INTO feedback (feedbackID, tp_number, deviceID, replied_by, message, admin_reply, submitted_at, replied_at) VALUES
('F001', 'TP095003', 'D004', 'TP090995', 'Quick and clear updates, thank you!', 'Thank you for recycling with us!', NOW() - INTERVAL 840 HOUR, NOW() - INTERVAL 816 HOUR),
('F002', 'TP095001', 'D008', NULL, 'My phone works again. Could the Green Hub open on Saturdays?', NULL, NOW() - INTERVAL 48 HOUR, NULL);

INSERT INTO notifications (notificationID, tp_number, created_by, title, message, is_read, created_at) VALUES
('N001', 'TP087791', NULL, 'Decision needed', 'Your ThinkPad T480 cannot be repaired. Please allow recycling or ask for it back.', 0, NOW() - INTERVAL 48 HOUR),
('N002', 'TP095002', NULL, 'Ready for collection', 'Your iPad Air 2 is repaired. Please collect it within 7 days.', 1, NOW() - INTERVAL 240 HOUR),
('N003', 'TP091586', 'TP090995', 'Request approved', 'You are now a Repair Volunteer.', 1, NOW() - INTERVAL 2112 HOUR),
('N004', 'TP095004', 'TP100002', 'Request rejected', 'Your volunteer request was not approved this time.', 1, NOW() - INTERVAL 456 HOUR),
-- Admin notifications (system-generated when a student asks for something an admin handles)
('N005', 'TP090995', NULL, 'New cashback request', 'Daniel Wong redeemed RM2 Cashback (100 points). Please pay it to the APCard.', 0, NOW() - INTERVAL 24 HOUR),
('N006', 'TP100002', NULL, 'New cashback request', 'Daniel Wong redeemed RM2 Cashback (100 points). Please pay it to the APCard.', 0, NOW() - INTERVAL 24 HOUR),
('N007', 'TP090995', NULL, 'New volunteer request', 'Siti Aminah asked to become a Repair Volunteer.', 0, NOW() - INTERVAL 48 HOUR),
('N008', 'TP100002', NULL, 'New volunteer request', 'Siti Aminah asked to become a Repair Volunteer.', 0, NOW() - INTERVAL 48 HOUR),
('N009', 'TP090995', NULL, 'New feedback', 'Daniel Wong sent feedback about Redmi Note 11.', 0, NOW() - INTERVAL 48 HOUR),
('N010', 'TP100002', NULL, 'New feedback', 'Daniel Wong sent feedback about Redmi Note 11.', 0, NOW() - INTERVAL 48 HOUR),
('N011', 'TP090995', 'TP100002', 'Challenge scheduled', 'I created the Final Week Cable Drive challenge. Please check the dates.', 1, NOW() - INTERVAL 24 HOUR),
('N012', 'TP090995', NULL, 'New feedback', 'Mei Ling Tan sent feedback about Galaxy Tab A.', 1, NOW() - INTERVAL 840 HOUR);
