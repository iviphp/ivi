-- 002_seed_users.sql
-- Insert 10 sample users into the `users` table

INSERT INTO users (name, email, password)
VALUES
  ('Alice Johnson',   'alice@example.com',   '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('Bob Smith',       'bob@example.com',     '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('Charlie Brown',   'charlie@example.com', '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('Diana Prince',    'diana@example.com',   '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('Ethan Carter',    'ethan@example.com',   '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('Fiona Adams',     'fiona@example.com',   '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('George Wilson',   'george@example.com',  '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('Hannah Baker',    'hannah@example.com',  '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('Ian Scott',       'ian@example.com',     '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef'),
  ('Julia Roberts',   'julia@example.com',   '$2y$10$abcdefabcdefabcdefabcdefabcdefabcdefabcdefabcdef');
