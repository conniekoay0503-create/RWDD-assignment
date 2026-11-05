-- =====================================================================
-- Volunteer pages - one small change to the team database
-- Run AFTER ewaste_repair_cafe.sql: phpMyAdmin > database ewaste_repair_cafe > SQL tab > paste > Go
--
-- Why: a device that no volunteer claims within 7 days goes back to its owner
-- (volunteer/repair_queue.php does this check). That needs a new status:
--   'Returned Unclaimed'  = "nobody repaired it, the owner collects it from the Green Hub"
-- It is added to BOTH places where the status list is stored.
-- Existing rows are not changed (the new value is only added at the end of each list).
-- =====================================================================
USE ewaste_repair_cafe;

ALTER TABLE devices MODIFY status
  ENUM('Submitted','Cancelled','Dropped Off','Claimed','In Repair','Ready for Collection','Completed',
       'Pending Consent','Return Requested','Awaiting Recycle','Recycled','Returned Unrepaired',
       'Collected Unrepaired','Disposed','Returned Unclaimed') NOT NULL DEFAULT 'Submitted';

ALTER TABLE device_status_history MODIFY new_status
  ENUM('Submitted','Cancelled','Dropped Off','Claimed','In Repair','Ready for Collection','Completed',
       'Pending Consent','Return Requested','Awaiting Recycle','Recycled','Returned Unrepaired',
       'Collected Unrepaired','Disposed','Returned Unclaimed') NOT NULL;
