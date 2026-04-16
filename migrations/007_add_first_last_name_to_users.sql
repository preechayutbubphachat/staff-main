ALTER TABLE users
  ADD COLUMN first_name VARCHAR(100) NULL AFTER fullname,
  ADD COLUMN last_name VARCHAR(100) NULL AFTER first_name;

UPDATE users
SET
  first_name = CASE
    WHEN COALESCE(first_name, '') <> '' THEN first_name
    WHEN COALESCE(fullname, '') = '' THEN NULL
    ELSE TRIM(SUBSTRING_INDEX(fullname, ' ', 1))
  END,
  last_name = CASE
    WHEN COALESCE(last_name, '') <> '' THEN last_name
    WHEN COALESCE(fullname, '') = '' THEN NULL
    ELSE NULLIF(TRIM(SUBSTRING(fullname, LENGTH(SUBSTRING_INDEX(fullname, ' ', 1)) + 1)), '')
  END;

ALTER TABLE users
  ADD KEY idx_users_first_name (first_name),
  ADD KEY idx_users_last_name (last_name);
