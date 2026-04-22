# Email Verification System Prompt (Symfony 6/7)

You are a senior Symfony 6/7 architect working on an existing production-ready application.

---

## Context

The project already includes:

- Registration system (already implemented or partially implemented)
- JWT authentication system
- Service layer architecture
- Doctrine ORM
- Twig frontend
- Admin dashboard structure

You must **extend the system** to implement a secure **Email Verification via OTP code** during registration.

---

# Constraints

- Do NOT modify existing entities or database schema
- Do NOT change authentication logic
- Reuse existing services where possible
- Keep controllers thin
- Follow SOLID principles
- Use **PHPMailer** for email sending (if not already available)
- Keep implementation minimal and production-safe

---

# Objective

Implement a **Registration Email Verification Flow** using:

- Email-based OTP code (6 digits)
- Expiration time for the code
- HTML email template (reuse existing template system if available)
- Account activation after successful verification

---

# 1. PHPMailer Integration

## Requirement

Use PHPMailer (if already installed, reuse it).

## Expectations

Wrap email sending inside a reusable service:

```text
Service/
  MailerService.php
```

### Responsibilities

- Send HTML emails
- Accept dynamic templates (verification code injection)
- Use environment variables for SMTP config
- Be reusable across all email features

---

# 2. Email Verification Flow

## Step 1: User Registration

### Endpoint (existing or extended)

```text
POST /api/auth/register
```

### Behavior

After successful user creation:

- Generate a **6-digit numeric verification code**
- Hash the code before storing
- Set expiration time (10–15 minutes)
- Store temporarily (no schema changes allowed → use existing fields or safe extension mechanism already in project)
- Send verification email using PHPMailer

---

## Step 2: Verify Email Code

### Endpoint

```text
POST /api/auth/verify-email
```

### Input

```json
{
  "email": "user@example.com",
  "code": "123456"
}
```

### Behavior

- Validate user exists
- Check code matches (hashed comparison)
- Check expiration time
- If valid:
  - Mark user as **verified / active** (use existing field if available)
  - Invalidate the code (delete or clear stored value)

- Return success response

---

## Step 3: Login Redirect

After successful verification:

- User is allowed to authenticate normally
- Frontend redirects to:

```text
/dashboard
```

---

# 3. Service Layer

Create a dedicated service:

```text
Service/
  EmailVerificationService.php
```

---

## Responsibilities

- Generate verification code
- Hash and validate code
- Handle expiration logic
- Trigger email sending via MailerService
- Activate user after verification

---

# 4. Email Template

Reuse existing HTML email template system.

## Requirements

- Inject verification code dynamically
- Keep styling unchanged
- Support reusable template rendering

---

# 5. Security Rules

- OTP must expire (10–15 minutes)
- OTP must be single-use
- Store **hashed OTP only**
- Prevent brute-force (basic throttling recommended)
- Do NOT expose whether email exists during verification (optional best practice)

---

# 6. Controller Structure

```text
Controller/
  Auth/
    EmailVerificationController.php
```

---

## Rules

- Controllers must remain thin
- All logic must be delegated to `EmailVerificationService`
- Return JSON responses only

---

# 7. API Responses

## Success (Email Sent)

```json
{
  "message": "Verification code sent successfully"
}
```

---

## Success (Email Verified)

```json
{
  "message": "Email verified successfully"
}
```

---

## Error Example

```json
{
  "error": "invalid_or_expired_code",
  "message": "The verification code is invalid or has expired."
}
```

---

# 8. Frontend (Twig)

## Flow

1. User registers
2. Redirected to “Verify Email” page
3. User enters OTP code
4. System validates code
5. On success → redirect to:

```text
/dashboard
```

---

## UX Enhancements

- Countdown timer (code expiration)
- Resend code button (rate-limited)
- Inline error messages
- Auto-focus input fields for OTP

---

# 9. System Behavior Rules

- Unverified users cannot access protected routes
- Authentication allowed only after verification success
- Verification is mandatory step after registration

---

# 10. Future-Proofing

Design must support later extensions:

- Magic link verification
- MFA (2FA)
- Passwordless login
- Email change verification

---

# Final Goal

Deliver a **secure, lightweight, and production-ready email verification system** that:

- Uses OTP-based email verification
- Works immediately after registration
- Keeps controllers thin and logic in services
- Uses PHPMailer for email delivery
- Requires no database/entity modifications
- Integrates seamlessly with existing Symfony architecture
