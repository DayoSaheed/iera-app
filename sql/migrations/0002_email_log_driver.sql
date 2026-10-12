-- Records which delivery method handled each email
ALTER TABLE email_log ADD COLUMN driver VARCHAR(20) NULL AFTER status;
