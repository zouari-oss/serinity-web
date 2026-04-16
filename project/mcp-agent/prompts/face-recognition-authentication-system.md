# Face Recognition Authentication System

You are a senior Symfony 8 architect working on an existing production-ready application.

---

## Context

The project already includes:

- User entity (email, password, roles, account status)
- JWT authentication system
- Service layer architecture
- Doctrine ORM
- Twig frontend
- Admin dashboard

### Existing Fields / Entities

- `users.face_recognition_enabled` (boolean flag)
- `user_faces` entity (stores face data)

You must **extend the system** to implement a secure **Face Recognition Authentication** feature.

---

# Constraints

- Do NOT rewrite existing authentication logic
- Integrate with current JWT system
- Reuse existing entities (`User`, `UserFace`)
- Keep controllers thin
- Follow SOLID principles
- Use ONNX Runtime (PHP) for inference
- Do NOT introduce unnecessary complexity
- Ensure strong security and privacy practices

---

# Objective

Implement a **Face Recognition Login System** that allows users to:

- Enable/disable face authentication
- Register their face (enrollment)
- Log in using face recognition

---

# 1. ONNX Integration

## Requirement

Use:

- ONNX Runtime PHP (`ankane/onnxruntime`)

## Expectations

- Wrap ONNX logic inside a dedicated service:

```text
Service/
  AI/
    FaceRecognitionService.php
```

## Responsibilities

- Load ONNX model (singleton)
- Process input image → embedding
- Compare embeddings (cosine similarity)

---

# 2. Feature Flow

---

## Step 1: Enable Face Recognition

### Endpoint

```text
POST /api/auth/face/enable
```

### Behavior

- Require authenticated user (JWT)
- Set:

```text
users.face_recognition_enabled = true
```

- Optionally trigger enrollment step

---

## Step 2: Face Enrollment

### Endpoint

```text
POST /api/auth/face/enroll
```

### Input

- Image (base64 or multipart)

### Behavior

- Validate `face_recognition_enabled = true`

- Detect and validate face presence

- Convert image → embedding (ONNX)

- Persist into `user_faces`:

```text
user_faces:
- id
- user_id
- embedding (longblob)
- created_at
- updated_at
```

## Rules

- One face per user (overwrite if exists)
- Do NOT store raw image (recommended)

---

## Step 3: Face Login

### Endpoint

```text
POST /api/auth/face/login
```

### Input

```json
{
    "image": "base64_image"
}
```

### Behavior

- Extract embedding from input image

- Compare with stored embeddings from `user_faces`

- Ensure:
  - User exists
  - `face_recognition_enabled = true`

- If similarity ≥ threshold:
  - Authenticate user
  - Return JWT token

---

# 3. Database Design

## Use Existing Entity

```text
user_faces
- id
- user (relation)
- embedding (JSON or vector)
- createdAt
- updatedAt
```

## Rules

- Enforce 1:1 relation with user
- Allow re-enrollment (update embedding)
- Ensure fast lookup (index on user_id)

---

# 4. Service Layer

## Create

```text
Service/
  AI/
    FaceRecognitionService.php
```

### Responsibilities

- Preprocess image (resize, normalize)
- Run ONNX model
- Generate embeddings
- Compare embeddings
- Handle similarity threshold (e.g. 0.85)

---

## Optional Helper

```text
Service/
  AI/
    FaceComparator.php
```

- Encapsulate similarity logic

---

# 5. Image Processing

## Requirements

- Resize (e.g. 112x112)
- Normalize (0–1 or -1 to 1)
- Validate:
  - Face exists
  - Single face only

---

# 6. Security Rules

- Use `face_recognition_enabled` as feature gate
- Store only embeddings (NOT raw images)
- Apply similarity threshold (0.8–0.9)
- Rate-limit login attempts
- Protect endpoints from abuse
- Always allow password fallback login

---

# 7. Controller Structure

```text
Controller/
  Auth/
    FaceAuthController.php
```

## Rules

- Controllers must remain thin
- Delegate logic to `FaceRecognitionService`
- Return JSON responses only

---

# 8. API Responses

## Success Example

```json
{
    "token": "jwt_token_here",
    "message": "Authentication successful"
}
```

## Error Example

```json
{
    "error": "face_not_recognized",
    "message": "Face does not match any registered user."
}
```

---

# 9. Frontend Integration (Twig / JS)

## Flow

1. User enables face recognition
2. Camera opens
3. Capture face image
4. Send to `/face/enroll`
5. On login:
    - Capture image
    - Send to `/face/login`

---

## UX Enhancements

- Face alignment overlay
- Real-time camera preview
- Retry on failure
- Clear error feedback

---

# 10. Performance & Scaling

## Requirements

- Load ONNX model once (singleton service)
- Avoid reloading per request
- Optimize embedding storage format
- Use async processing if needed (Messenger)

---

# 11. Future-Proofing

Structure must support:

```text
/api/auth/face/*
```

Future extensions:

- Liveness detection (anti-spoofing)
- MFA (Face + OTP)
- Multiple face profiles per user
- Device-based trust system

---

# Final Goal

Deliver a **secure, minimal, and scalable face recognition system** that:

- Uses ONNX Runtime in Symfony
- Leverages existing `users.face_recognition_enabled` and `user_faces`
- Supports enrollment + authentication
- Stores embeddings securely
- Integrates seamlessly with JWT authentication
- Keeps controllers thin and logic centralized
