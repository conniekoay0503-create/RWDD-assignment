-- =====================================================================
-- OPTIONAL test data: two more volunteers so the Challenge & rank leaderboard has more than one row.
-- Run AFTER ewaste_repair_cafe.sql. Safe to skip. Not needed for the real demo data.
--   TP095003 Mei Ling Tan and TP095002 Priya Suresh become volunteers (password Password123, as for every demo account).
-- =====================================================================
USE ewaste_repair_cafe;

INSERT INTO volunteers (tp_number, assigned_by, assigned_at) VALUES
  ('TP095003', 'TP090995', NOW() - INTERVAL 30 DAY),
  ('TP095002', 'TP090995', NOW() - INTERVAL 20 DAY);

INSERT INTO points_transactions (transactionID, tp_number, deviceID, redemptionID, challengeID, points, txn_type, description, created_at) VALUES
  ('T101', 'TP095003', NULL, NULL, NULL, 320, 'earn', 'Test points for the leaderboard', NOW() - INTERVAL 10 DAY),
  ('T102', 'TP095002', NULL, NULL, NULL, 180, 'earn', 'Test points for the leaderboard', NOW() - INTERVAL 6 DAY);
