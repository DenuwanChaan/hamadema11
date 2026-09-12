-- ============================================================
-- Hamadema — Fix: category icons were truncated (icon column
-- was VARCHAR(10), too short for class names like "fi fi-rr-car-side").
-- Run this in phpMyAdmin. Safe to re-run.
-- ============================================================

ALTER TABLE categories MODIFY icon VARCHAR(40) NULL;

-- These class names have been verified against the live Flaticon uicons CDN
-- (the same one already loaded in header.php), so they will actually render.
UPDATE categories SET icon = 'fi fi-rr-car-side',        sort_order = 1  WHERE slug = 'vehicles';
UPDATE categories SET icon = 'fi fi-rr-home',            sort_order = 2  WHERE slug = 'property';
UPDATE categories SET icon = 'fi fi-rr-mobile-notch',    sort_order = 3  WHERE slug = 'mobile';
UPDATE categories SET icon = 'fi fi-rr-bulb',             sort_order = 4  WHERE slug = 'electronics';
UPDATE categories SET icon = 'fi fi-rr-chair',            sort_order = 5  WHERE slug = 'home-garden';
UPDATE categories SET icon = 'fi fi-rr-person-dress',     sort_order = 6  WHERE slug = 'fashion';
UPDATE categories SET icon = 'fi fi-rr-briefcase',        sort_order = 7  WHERE slug = 'jobs';
UPDATE categories SET icon = 'fi fi-rr-settings-sliders', sort_order = 8  WHERE slug = 'services';
UPDATE categories SET icon = 'fi fi-rr-paw',              sort_order = 9  WHERE slug = 'pets';
UPDATE categories SET icon = 'fi fi-rr-baseball',         sort_order = 10 WHERE slug = 'hobby-sport-kids';

-- If sort_order column doesn't exist yet (i.e. you never ran the earlier
-- schema-update.sql), uncomment the line below first, then re-run this file:
-- ALTER TABLE categories ADD COLUMN sort_order INT DEFAULT 0;