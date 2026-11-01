import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';

// Reads QR codes and the common retail barcodes from the device camera.
// A USB/Bluetooth scanner types the code into the manual input instead.
const FORMATS = [
    Html5QrcodeSupportedFormats.QR_CODE,
    Html5QrcodeSupportedFormats.EAN_13,
    Html5QrcodeSupportedFormats.EAN_8,
    Html5QrcodeSupportedFormats.UPC_A,
    Html5QrcodeSupportedFormats.UPC_E,
    Html5QrcodeSupportedFormats.CODE_128,
    Html5QrcodeSupportedFormats.CODE_39,
];

// A code counts again only after it has been out of view this long,
// so a label held in front of the camera registers once, not every second.
const RELEASE_MS = 1200;

// target: optional Livewire property to fill (e.g. 'form.code'); the camera then stops after one read.
// Without a target every read is sent to the component's scanned(code) method.
export default (elementId, target = null) => ({
    scanner: null,
    running: false,
    error: null,
    manualCode: '',
    lastCode: null,
    lastSeenAt: 0,
    lastResult: null,

    async start() {
        this.error = null;
        // Show the viewfinder before starting: the library measures it and fails on a hidden element.
        this.running = true;
        await this.$nextTick();
        try {
            this.scanner = new Html5Qrcode(elementId, {
                formatsToSupport: FORMATS,
                // Use the browser's native detector where it exists (Android/Chrome OS/macOS): far faster on real cameras.
                experimentalFeatures: { useBarCodeDetectorIfSupported: true },
                verbose: false,
            });
            // A scan box is cropped at its on-screen size; in a narrow viewfinder that shrinks barcode bars
            // below what the decoder can read, so small viewfinders scan the whole frame instead.
            const narrow = document.getElementById(elementId)?.clientWidth < 420;
            await this.scanner.start(
                { facingMode: 'environment' },
                {
                    fps: 12,
                    // Landscape 4:3 frame: a phone's back camera otherwise streams portrait and fills the screen.
                    aspectRatio: 4 / 3,
                    // Wide, short box suits 1D barcodes and still fits a QR label.
                    qrbox: narrow
                        ? undefined
                        : (w, h) => ({
                              width: Math.floor(w * 0.85),
                              height: Math.floor(Math.min(h * 0.6, w * 0.85)),
                          }),
                    videoConstraints: {
                        facingMode: 'environment',
                        width: { ideal: 1280 },
                        height: { ideal: 720 },
                        advanced: [{ focusMode: 'continuous' }],
                    },
                },
                (code) => this.detected(code),
                () => this.notDetected()
            );
            this.running = true;
        } catch (e) {
            this.error =
                'Camera unavailable: ' + (e?.message ?? e) + '. Use the code box below instead.';
            this.running = false;
        }
    },

    async stop() {
        if (this.scanner && this.running) {
            await this.scanner.stop();
        }
        this.running = false;
    },

    detected(code) {
        const now = Date.now();
        const sameCodeStillInView = code === this.lastCode && now - this.lastSeenAt < RELEASE_MS;
        this.lastCode = code;
        this.lastSeenAt = now;

        if (!sameCodeStillInView) {
            this.submit(code);
        }
    },

    notDetected() {
        // Called for every frame without a code; once the label has been gone a moment, it may be scanned again.
        if (this.lastCode && Date.now() - this.lastSeenAt >= RELEASE_MS) {
            this.lastCode = null;
        }
    },

    submitManual() {
        const code = this.manualCode.trim();
        this.manualCode = '';
        if (code) {
            this.submit(code);
        }
    },

    submit(code) {
        this.lastResult = code;
        this.feedback();

        if (target) {
            this.$wire.set(target, code);
            this.stop();

            return;
        }

        this.$wire.scanned(code);
    },

    // Short beep + vibration so staff know the scan registered without looking at the screen.
    feedback() {
        try {
            navigator.vibrate?.(80);
            const ctx = (this.audio ??= new (window.AudioContext || window.webkitAudioContext)());
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.frequency.value = 1200;
            gain.gain.value = 0.08;
            osc.connect(gain).connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.08);
        } catch (e) {
            // Feedback is a nicety; scanning works without it.
        }
    },

    destroy() {
        this.stop();
    },
});
