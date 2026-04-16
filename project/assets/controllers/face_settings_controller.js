import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
  static targets = ['video', 'status', 'state'];
  static values = {
    enabled: Boolean,
  };

  connect() {
    this.stream = null;
    this.autoCaptureTimer = null;
    this.frameSampler = null;
    this.liveDetectionRaf = null;
    this.liveDetectionCanvas = null;
    this.faceDetector = null;
    this.isDetectingFace = false;
    this.lastDetectedFace = null;
    this.faceDetectorUnavailable = false;
    this.renderState();
  }

  disconnect() {
    this.clearAutoCapture();
    this.stopCamera();
  }

  async enable() {
    const enabled = await this.toggle(true);
    if (enabled) {
      await this.startTimedBestFaceCapture(10_000);
    }
  }

  async disable() {
    await this.toggle(false);
  }

  async toggle(enabled) {
    const endpoint = enabled ? '/api/auth/face/enable' : '/api/auth/face/disable';
    const payload = enabled ? { enabled: true } : {};

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        credentials: 'include',
      });

      const data = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
      if (!response.ok || !data.success) {
        this.showStatus(data.message || 'Unable to update face authentication state.', 'error');
        return false;
      }

      this.enabledValue = enabled;
      this.renderState();
      this.showStatus(data.message || 'Face authentication updated.', 'success');
      return true;
    } catch (_error) {
      this.showStatus('Network error. Please try again.', 'error');
      return false;
    }
  }

  async startCameraStream() {
    if (!navigator.mediaDevices?.getUserMedia) {
      this.showStatus('Camera access is not available on this browser.', 'error');
      return false;
    }

    if (!this.enabledValue) {
      this.showStatus('Enable face authentication before enrollment.', 'error');
      return false;
    }

    try {
      this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
      if (this.hasVideoTarget) {
        this.videoTarget.srcObject = this.stream;
        this.videoTarget.hidden = false;
      }
      this.startSilentFaceTracking();
      this.showStatus('Camera ready.', 'success');
      return true;
    } catch (_error) {
      this.showStatus('Unable to access camera. Please check permissions.', 'error');
      return false;
    }
  }

  async startTimedBestFaceCapture(durationMs = 10_000) {
    this.clearAutoCapture();

    const ready = await this.startCameraStream();
    if (!ready) {
      return;
    }

    this.bestFrame = null;
    this.bestTensor = null;
    this.bestMetric = 0;
    this.topSnapshots = [];
    this.showStatus('Analyzing camera frames for 10 seconds to pick the best face quality metric...', 'success');

    this.frameSampler = window.setInterval(() => {
      const snapshot = this.captureFrameSnapshot();
      if (!snapshot) return;
      this.insertTopSnapshot(snapshot);
    }, 200);

    this.autoCaptureTimer = window.setTimeout(async () => {
      this.clearAutoCapture();
      this.stopCamera();

      if (!this.topSnapshots || this.topSnapshots.length === 0) {
        this.showStatus('No usable frame detected. Please retry enrollment.', 'error');
        return;
      }
      const bestSnapshot = this.topSnapshots[0];
      const averagedTensor = this.averageTensors(this.topSnapshots.map((snapshot) => snapshot.tensor));
      if (!averagedTensor) {
        this.showStatus('Unable to build stable enrollment embedding. Please retry.', 'error');
        return;
      }

      await this.enrollImage(bestSnapshot.image, averagedTensor, bestSnapshot.metric);
    }, durationMs);
  }

  stopCamera() {
    this.clearAutoCapture();
    this.stopSilentFaceTracking();
    this.lastDetectedFace = null;
    if (!this.stream) return;
    this.stream.getTracks().forEach((track) => track.stop());
    this.stream = null;
    if (this.hasVideoTarget) {
      this.videoTarget.srcObject = null;
      this.videoTarget.hidden = true;
    }
  }

  captureFrameSnapshot() {
    if (!this.hasVideoTarget) return null;

    const video = this.videoTarget;
    const width = video.videoWidth || 320;
    const height = video.videoHeight || 240;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d');
    if (!context) return null;
    context.drawImage(video, 0, 0, width, height);
    const metric = this.computeFaceQualityMetric(context, width, height);
    return {
      image: canvas.toDataURL('image/jpeg', 0.92),
      metric,
      tensor: this.buildTensorFromCanvas(canvas),
    };
  }

  buildTensorFromCanvas(sourceCanvas) {
    const inputWidth = 112;
    const inputHeight = 112;
    const canvas = document.createElement('canvas');
    canvas.width = inputWidth;
    canvas.height = inputHeight;
    const context = canvas.getContext('2d');
    if (!context) return null;

    const sourceWidth = sourceCanvas.width || inputWidth;
    const sourceHeight = sourceCanvas.height || inputHeight;
    const crop = this.resolveFaceCrop(sourceWidth, sourceHeight);

    context.drawImage(
      sourceCanvas,
      crop.x,
      crop.y,
      crop.size,
      crop.size,
      0,
      0,
      inputWidth,
      inputHeight,
    );
    const frame = context.getImageData(0, 0, inputWidth, inputHeight).data;
    const r = [];
    const g = [];
    const b = [];
    for (let y = 0; y < inputHeight; y += 1) {
      const rRow = [];
      const gRow = [];
      const bRow = [];
      for (let x = 0; x < inputWidth; x += 1) {
        const i = (y * inputWidth + x) * 4;
        rRow.push((frame[i] - 127.5) / 128);
        gRow.push((frame[i + 1] - 127.5) / 128);
        bRow.push((frame[i + 2] - 127.5) / 128);
      }
      r.push(rRow);
      g.push(gRow);
      b.push(bRow);
    }

    return [[r, g, b]];
  }

  computeFaceQualityMetric(context, width, height) {
    const frame = context.getImageData(0, 0, width, height).data;
    const sampleStep = 4;
    let brightnessSum = 0;
    let brightnessSquaredSum = 0;
    let edgeSum = 0;
    let count = 0;
    let lastLuma = null;

    for (let i = 0; i < frame.length; i += sampleStep * 4) {
      const r = frame[i];
      const g = frame[i + 1];
      const b = frame[i + 2];
      const luma = 0.299 * r + 0.587 * g + 0.114 * b;
      brightnessSum += luma;
      brightnessSquaredSum += luma * luma;
      if (lastLuma !== null) {
        edgeSum += Math.abs(luma - lastLuma);
      }
      lastLuma = luma;
      count += 1;
    }

    if (count === 0) {
      return 0;
    }

    const brightnessMean = brightnessSum / count;
    const brightnessVariance = Math.max(0, brightnessSquaredSum / count - brightnessMean * brightnessMean);
    const brightnessStd = Math.sqrt(brightnessVariance);
    const brightnessScore = this.clamp(1 - Math.abs(brightnessMean - 128) / 128, 0, 1);
    const contrastScore = this.clamp(brightnessStd / 48, 0, 1);
    const sharpnessScore = this.clamp((edgeSum / count) / 16, 0, 1);

    return this.clamp(brightnessScore * 0.2 + contrastScore * 0.35 + sharpnessScore * 0.45, 0, 1);
  }

  clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
  }

  insertTopSnapshot(snapshot) {
    if (!snapshot || !snapshot.tensor || typeof snapshot.metric !== 'number') {
      return;
    }

    this.topSnapshots.push(snapshot);
    this.topSnapshots.sort((left, right) => right.metric - left.metric);
    if (this.topSnapshots.length > 5) {
      this.topSnapshots.length = 5;
    }
    this.bestFrame = this.topSnapshots[0]?.image ?? null;
    this.bestTensor = this.topSnapshots[0]?.tensor ?? null;
    this.bestMetric = this.topSnapshots[0]?.metric ?? 0;
  }

  averageTensors(tensors) {
    if (!Array.isArray(tensors) || tensors.length === 0) {
      return null;
    }

    return this.averageNestedValues(tensors);
  }

  averageNestedValues(values) {
    const first = values[0];
    if (!Array.isArray(first)) {
      let sum = 0;
      for (const value of values) {
        sum += Number(value) || 0;
      }

      return sum / values.length;
    }

    const length = first.length;
    const averaged = [];
    for (let index = 0; index < length; index += 1) {
      averaged.push(this.averageNestedValues(values.map((value) => value[index])));
    }

    return averaged;
  }

  resolveFaceCrop(sourceWidth, sourceHeight) {
    if (this.lastDetectedFace) {
      const expand = 1.1;
      const centerX = this.lastDetectedFace.x + this.lastDetectedFace.width / 2;
      const centerY = this.lastDetectedFace.y + this.lastDetectedFace.height / 2;
      const size = Math.max(this.lastDetectedFace.width, this.lastDetectedFace.height) * expand;
      const safeSize = Math.max(1, Math.min(size, Math.min(sourceWidth, sourceHeight)));
      const x = Math.max(0, Math.min(centerX - safeSize / 2, sourceWidth - safeSize));
      const y = Math.max(0, Math.min(centerY - safeSize / 2, sourceHeight - safeSize));

      return {
        x: Math.floor(x),
        y: Math.floor(y),
        size: Math.floor(safeSize),
      };
    }

    const size = Math.max(1, Math.floor(Math.min(sourceWidth, sourceHeight) * 0.82));
    return {
      x: Math.max(0, Math.floor((sourceWidth - size) / 2)),
      y: Math.max(0, Math.floor((sourceHeight - size) / 2)),
      size,
    };
  }

  async detectFaceInCanvas(sourceCanvas) {
    if (this.isDetectingFace || this.faceDetectorUnavailable || !('FaceDetector' in window)) {
      if (!('FaceDetector' in window)) {
        this.faceDetectorUnavailable = true;
      }
      return;
    }

    this.isDetectingFace = true;
    try {
      if (!this.faceDetector) {
        this.faceDetector = new window.FaceDetector({
          fastMode: false,
          maxDetectedFaces: 1,
        });
      }

      const faces = await this.faceDetector.detect(sourceCanvas);
      const box = faces[0]?.boundingBox ?? null;
      this.lastDetectedFace = box ? {
        x: box.x,
        y: box.y,
        width: box.width,
        height: box.height,
      } : null;
    } catch (_error) {
      this.faceDetectorUnavailable = true;
      this.lastDetectedFace = null;
    } finally {
      this.isDetectingFace = false;
    }
  }

  startSilentFaceTracking() {
    if (!this.stream || !this.hasVideoTarget || this.faceDetectorUnavailable) {
      return;
    }

    const loop = async () => {
      if (!this.stream || !this.hasVideoTarget) {
        return;
      }

      const video = this.videoTarget;
      const width = video.videoWidth || 0;
      const height = video.videoHeight || 0;
      if (width > 0 && height > 0) {
        if (!this.liveDetectionCanvas) {
          this.liveDetectionCanvas = document.createElement('canvas');
        }
        if (this.liveDetectionCanvas.width !== width) {
          this.liveDetectionCanvas.width = width;
        }
        if (this.liveDetectionCanvas.height !== height) {
          this.liveDetectionCanvas.height = height;
        }

        const context = this.liveDetectionCanvas.getContext('2d');
        if (context) {
          context.drawImage(video, 0, 0, width, height);
          await this.detectFaceInCanvas(this.liveDetectionCanvas);
        }
      }

      this.liveDetectionRaf = window.requestAnimationFrame(() => {
        loop();
      });
    };

    this.stopSilentFaceTracking();
    this.liveDetectionRaf = window.requestAnimationFrame(() => {
      loop();
    });
  }

  stopSilentFaceTracking() {
    if (this.liveDetectionRaf !== null) {
      window.cancelAnimationFrame(this.liveDetectionRaf);
      this.liveDetectionRaf = null;
    }
  }

  async enrollImage(image, tensor, metric = null) {
    try {
      const response = await fetch('/api/auth/face/enroll', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ image, tensor }),
        credentials: 'include',
      });

      const payload = await response.json().catch(() => ({ success: false, message: 'Invalid server response.' }));
      if (!response.ok || !payload.success) {
        this.showStatus(payload.message || 'Enrollment failed.', 'error');
        return;
      }

      this.showStatus(payload.message || 'Face enrolled successfully.', 'success');
    } catch (_error) {
      this.showStatus('Network error. Please try again.', 'error');
    }
  }

  clearAutoCapture() {
    if (this.frameSampler !== null) {
      window.clearInterval(this.frameSampler);
      this.frameSampler = null;
    }
    if (this.autoCaptureTimer !== null) {
      window.clearTimeout(this.autoCaptureTimer);
      this.autoCaptureTimer = null;
    }
  }

  renderState() {
    if (!this.hasStateTarget) return;
    this.stateTarget.textContent = this.enabledValue ? 'Enabled' : 'Disabled';
    this.stateTarget.classList.remove('ac-badge-success', 'ac-badge-secondary');
    this.stateTarget.classList.add(this.enabledValue ? 'ac-badge-success' : 'ac-badge-secondary');
  }

  showStatus(message, type) {
    if (!this.hasStatusTarget) return;
    this.statusTarget.textContent = message;
    this.statusTarget.hidden = false;
    this.statusTarget.classList.remove('ac-inline-success', 'ac-inline-error');
    this.statusTarget.classList.add(type === 'success' ? 'ac-inline-success' : 'ac-inline-error');
  }
}
