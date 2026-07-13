<?php

return [

    // General
    'success' => 'Operation completed successfully.',
    'created' => 'Data created successfully.',
    'updated' => 'Data updated successfully.',
    'deleted' => 'Data deleted successfully.',
    'not_found' => 'Resource not found.',
    'method_not_allowed' => 'HTTP method is not allowed for this endpoint.',
    'server_error' => 'Unexpected server error.',
    'validation_failed' => 'Validation failed.',

    // Authentication
    'register_success' => 'Account created successfully.',
    'email_registration_success' => 'Account created successfully, a verification code has been sent to your email.',
    'login_success' => 'Logged in successfully.',
    'logout_success' => 'Logged out successfully.',
    'password_reset_success' => 'Password reset successfully.',
    'password_reset_code_required' => 'The password reset code must be verified first.',
    // User
    'user_not_found' => 'User not found.',
    'profile_updated' => 'Profile updated successfully.',

    // Verification
    'verification_code_sent' => 'Verification code sent successfully.',
    'verification_code_already_sent' => 'A valid verification code has already been sent. Please wait until it expires.',
    'verification_code_not_found' => 'No valid verification code was found.',
    'verification_success' => 'Verification completed successfully.',
    'verification_failed' => 'Invalid verification code.',
    'verification_expired' => 'Verification code has expired.',
    'verification_max_attempts' => 'The maximum number of verification attempts has been reached.',
    'verification_email_subject' => 'Your verification code',
    'password_reset_email_subject' => 'Your password reset code',
    'verification_email_intro' => 'Use the following code to complete your verification.',
    'verification_email_code' => 'Verification code: :code',
    'verification_email_expires_at' => 'This code expires at :expires_at.',

    'invalid_reset_token' => 'The reset password token is invalid.',

    'reset_token_expired' => 'The reset password token has expired.',
];
