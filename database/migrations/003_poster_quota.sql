-- Migration 003: Poster Pipeline Types in Generations
ALTER TABLE `generations` MODIFY COLUMN `type` ENUM('enhance','generate','edit','poster_import','poster_generate') NOT NULL;

