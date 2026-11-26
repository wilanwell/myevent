-- Phase 10: each account remembers its language (en / ms); emails are written in it.
ALTER TABLE users
  ADD COLUMN locale VARCHAR(5) DEFAULT NULL COMMENT 'Interface and email language: en or ms (NULL = not chosen yet)' AFTER email_organiser;
