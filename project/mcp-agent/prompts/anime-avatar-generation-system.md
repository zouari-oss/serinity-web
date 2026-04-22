# Anime Avatar Generation System (Img2Img - Symfony 6/7/8)

You are a senior Symfony 6/7/8 architect working on an existing production-ready application.

---

## Context

The project already includes:

- User entity (profile image, email, roles, etc.)
- JWT authentication system
- Service layer architecture
- Doctrine ORM
- Twig frontend
- API-first design
- User dashboard UI

You must **extend the system** to generate **anime-style avatars** from user profile images using a **fully local AI pipeline**, and display results directly in the **user dashboard with a modern UX**.

---

# Constraints

- Do NOT modify existing user upload logic
- Do NOT store generated avatars in database or filesystem
- Keep controllers thin
- Respect existing service architecture
- Use HTTP integration (no direct Python execution inside Symfony)
- System must be **fully local (no external APIs)**
- Avatar generation must be **non-blocking (async from frontend perspective)**
- Follow SOLID principles
- Ensure performance and fault tolerance

---

# Objective

Implement **AI-based avatar generation (img2img)**:

- Convert user profile image → anime avatar
- Use local inference API
- Return generated image dynamically
- Display result in dashboard
- Ensure smooth UX without blocking page rendering
- Automatically trigger avatar generation when profile image is added or updated (without persisting result)

---

# 1. External Service

Use:

- img2img-api ([https://github.com/zouari-oss/img2img-api](https://github.com/zouari-oss/img2img-api))

---

## Responsibilities

- Accept image + prompt
- Run Stable Diffusion img2img
- Return generated image (base64)

---

# 2. System Architecture

```text
Symfony (PHP)
    ↓ (auto trigger OR AJAX)
Avatar Generation Trigger (Event / Frontend)
    ↓
Img2Img API (localhost:8000)
    ↓
Stable Diffusion (img2img)
    ↓
Generated anime avatar (base64)
    ↓
Returned to frontend (NOT stored)
    ↓
Rendered dynamically in dashboard
```

---

# 3. API Contract

## Endpoint

```text
POST /api/avatar/generate
```

## Request

```json
{
  "image_url": "string",
  "prompt": "string",
  "negative_prompt": "string",
  "strength": 0.6
}
```

---

## Response

```json
{
  "image": "base64_encoded_image"
}
```

---

# 4. Symfony Integration

## Service Layer

```text
Service/
  Avatar/
    AvatarGenerator.php
```

---

## Responsibilities

- Call Img2Img API
- Handle request/response
- Manage prompt presets
- Handle errors & timeouts
- Keep logic outside controllers

---

## Example Implementation

```php
class AvatarGenerator
{
    public function generate(string $imageUrl): string
    {
        $payload = [
            'image_url' => $imageUrl,
            'prompt' => 'anime portrait, high quality, soft lighting, detailed face, studio lighting',
            'negative_prompt' => 'blurry, low quality, distorted face, extra limbs, bad anatomy',
            'strength' => 0.65,
        ];

        // HTTP call using Symfony HttpClient
        // Return base64 image
    }
}
```

---

# 5. Controller

```text
Controller/
  Api/
    AvatarController.php
```

---

## Responsibilities

- Receive AJAX request
- Call AvatarGenerator
- Return JSON response

---

## Endpoint

```text
POST /api/avatar/generate
```

---

## Behavior

- Requires authenticated user
- Uses user profile image
- Returns base64 avatar
- Stateless (no persistence)

---

# 6. Automatic Generation Trigger (NEW)

## Goal

Trigger avatar generation when:

- User uploads a profile image
- User updates profile image

---

## Implementation Strategy (Without Modifying Upload Logic)

Use:

- Doctrine Event Listener (`postPersist`, `postUpdate`)
  OR
- Domain Event Subscriber (preferred if already used)

---

## Flow

```text
Profile Image Updated
        ↓
Doctrine/Event Subscriber detects change
        ↓
Frontend is informed (or polls)
        ↓
Frontend triggers /api/avatar/generate
        ↓
Avatar generated (base64 only)
```

---

## Important Rule

- ❌ Do NOT generate avatar inside Doctrine listener (blocking risk)
- ❌ Do NOT store avatar
- ✅ Only trigger generation indirectly (frontend or async call)

---

# 7. Frontend Integration (Dashboard UX)

## Requirements

- Avatar generation must NOT block page load
- Use async request (Fetch / AJAX)
- Show loading state
- Replace avatar dynamically when ready
- Support auto-generation after upload

---

## Example (Twig + JS)

```twig
<div id="avatar-container">
    <img id="avatar-preview" src="{{ user.profileImage }}" alt="avatar">

    <button id="generate-avatar">Generate Anime Avatar</button>

    <div id="loader" style="display:none;">
        Generating...
    </div>
</div>

<script>
async function generateAvatar() {
    const loader = document.getElementById('loader');
    const img = document.getElementById('avatar-preview');
    const btn = document.getElementById('generate-avatar');

    loader.style.display = 'block';
    btn.disabled = true;

    try {
        const response = await fetch('/api/avatar/generate', {
            method: 'POST'
        });

        const data = await response.json();

        if (data.image) {
            img.style.opacity = 0;
            setTimeout(() => {
                img.src = 'data:image/png;base64,' + data.image;
                img.style.opacity = 1;
            }, 200);
        }

    } catch (e) {
        console.error('Avatar generation failed');
    }

    loader.style.display = 'none';
    btn.disabled = false;
}

document.getElementById('generate-avatar')
    .addEventListener('click', generateAvatar);

// Optional: auto-trigger after upload
window.addEventListener('load', () => {
    // if profile image was recently updated
    // generateAvatar();
});
</script>
```

---

## UX Guidelines (Pro UI/UX)

- Show skeleton loader or spinner during generation
- Disable button while processing
- Provide retry option on failure
- Smooth fade-in transition
- Keep original avatar visible until new one loads
- Ensure responsive design
- Optional auto-trigger after profile update

---

# 8. Storage Rules

- Do NOT store generated avatars
- Do NOT persist in database
- Do NOT cache permanently
- Image exists only in frontend memory (base64)

---

# 9. Prompt Strategy

```text
anime portrait, high quality, soft lighting, detailed face, studio lighting
```

## Negative Prompt

```text
blurry, low quality, distorted face, extra limbs, bad anatomy
```

---

# 10. Error Handling

## Example

```json
{
  "error": "generation_failed",
  "message": "Unable to generate avatar"
}
```

---

## Cases

- API unavailable
- Timeout
- Invalid image
- Empty response

---

# 11. Performance Considerations

- Generation time: 2–15 seconds
- Must NOT block UI
- Use async frontend calls
- Avoid backend blocking (no sync generation in events)

---

# 12. Security Rules

- Only authenticated users
- Validate image URL (prevent SSRF)
- Rate limit requests
- Sanitize inputs
- Prevent abuse

---

# 13. Optional Enhancements

- Multiple avatar styles
- Regenerate button
- Temporary caching (short-lived, optional)
- Progress indicator (if backend supports it)
- WebSocket updates (advanced)

---

# 14. Future-Proofing

```text
/api/avatar/*
```

Future features:

- Avatar history (if storage allowed later)
- Style selector (anime, cartoon, realistic)
- Face consistency improvements (LoRA / ControlNet)
- Background removal

---

# Final Goal

Deliver a **modern, non-blocking, and fully local avatar generation system** that:

- Uses img2img-api
- Integrates seamlessly with Symfony
- Automatically reacts to profile image updates
- Displays avatars dynamically in the dashboard
- Provides smooth UX (async + dynamic rendering)
- Does NOT store generated images
- Maintains performance and security best practices
