ALTER TABLE users
  ADD COLUMN profile_image_path VARCHAR(255) DEFAULT NULL AFTER signature_path;

