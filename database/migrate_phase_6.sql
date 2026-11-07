-- AI Design Studio - Phase 6 Migration
ALTER TABLE `generations` MODIFY COLUMN `type` ENUM('enhance','generate','edit','poster_import','poster_generate') NOT NULL;

