#######################################################################################
# modeltest_rtdt2onnx.py                                                              #
# Copyright Andrew Wood 2026                                                          #
# Licensed under GNU Public License 3                                                 #
#                                                                                     #
# Run the model against the specified video stream                                    #
# to display live view of what it can detect                                          #
#                                                                                     #
# Adjust CONF_THRES to lower threshold and HIGHER_THRES to higher threshold           #
#                                                                                     #
#                                                                                     #
# Performance layout: one thread reads the stream (always keeping only the newest     #
# frame), one thread runs inference on the newest frame as fast as the hardware       #
# allows, and the main thread displays each processed frame together with ITS OWN     #
# detections, so boxes always line up. Set BB_FORCE_CPU=1 in the environment to       #
# skip NPU/GPU providers.                                                             #
#                                                                                     #
#######################################################################################

import cv2
import numpy as np
import sys
import py7zr
import os
import io
import time
import threading
import onnxruntime as ort


#=======================================================
#Stupid API change in Py7zr forces us to do it this way now
class MemIO:
    def __init__(self, filename):
        self.filename = filename
        self._buf = io.BytesIO()

    def write(self, s):
        return self._buf.write(s)

    def read(self, size=None):
        return self._buf.read() if size is None else self._buf.read(size)

    def seek(self, offset, whence=0):
        return self._buf.seek(offset, whence)

    def tell(self):
        return self._buf.tell()

    def flush(self):
        pass

    def close(self):
        pass  # keep the buffer alive so getvalue() works afterwards

    def size(self):
        return len(self._buf.getbuffer())

    def getvalue(self):
        return self._buf.getvalue()
#=======================================================

#=======================================================
#Stupid API change in Py7zr forces us to do it this way now
class MemFactory:
    def __init__(self):
        self.files = {}

    def create(self, filename):
        f = MemIO(filename)
        self.files[filename] = f
        return f
#=======================================================


#=======================================================
def makeSessionOptions():
    so = ort.SessionOptions()
    so.graph_optimization_level = ort.GraphOptimizationLevel.ORT_ENABLE_ALL
    so.execution_mode = ort.ExecutionMode.ORT_SEQUENTIAL
    # Physical cores are usually faster than all logical threads on hyperthreaded CPUs.
    # Try changing this divisor to 1 to compare.
    so.intra_op_num_threads = max(1, (os.cpu_count() or 2) // 2)
    return so
#=======================================================


#=======================================================
def tryONNXRuntimeHardware(modelbytes):
    """Create and return (session, providers) using the best provider that works."""
    available = ort.get_available_providers()
    candidates = []

    if os.environ.get("BB_FORCE_CPU") != "1":
        if "OpenVINOExecutionProvider" in available:
            candidates.append([("OpenVINOExecutionProvider", {"device_type": "NPU"})])
        if "CoreMLExecutionProvider" in available:
            candidates.append(["CoreMLExecutionProvider"])
        if "CUDAExecutionProvider" in available:
            candidates.append(["CUDAExecutionProvider"])
        if "MIGraphXExecutionProvider" in available:
            candidates.append(["MIGraphXExecutionProvider"])

    if "CPUExecutionProvider" in available:
        candidates.append(["CPUExecutionProvider"])

    so = makeSessionOptions()

    for providers in candidates:
        try:
            print("Trying to run:", providers)
            # Always append CPU as a fallback for any ops the accelerator can't handle
            if providers != ["CPUExecutionProvider"]:
                full = providers + ["CPUExecutionProvider"]
            else:
                full = providers
            session = ort.InferenceSession(modelbytes, sess_options=so, providers=full)
            print("Using:", session.get_providers())
            return session, providers
        except Exception as e:
            print("Failed to run:", providers, e)

    print("Could not create ONNX Runtime session")
    sys.exit(1)
#=======================================================


#========================================================
def gui_available():
    # On Linux without a display, some OpenCV builds abort instead of raising
    if sys.platform.startswith("linux"):
        if not (os.environ.get("DISPLAY") or os.environ.get("WAYLAND_DISPLAY")):
            return False
    try:
        cv2.namedWindow("__test__", cv2.WINDOW_NORMAL)
        cv2.destroyWindow("__test__")
        return True
    except cv2.error:
        return False
#=======================================================


#=======================================================
class LatestFrame:
    """Reads the stream in a background thread, keeping only the newest frame."""

    def __init__(self, url):
        self.url = url
        self.cap = cv2.VideoCapture(url, cv2.CAP_FFMPEG)
        if not self.cap.isOpened():
            raise RuntimeError("Cannot open video")
        self.frame = None
        self.fid = 0
        self.lock = threading.Lock()
        self.running = True
        self.thread = threading.Thread(target=self._run, daemon=True)
        self.thread.start()

    def _run(self):
        while self.running:
            ok, f = self.cap.read()
            if not ok:
                print("Reconnecting stream...")
                self.cap.release()
                time.sleep(2)
                self.cap = cv2.VideoCapture(self.url, cv2.CAP_FFMPEG)
                continue
            with self.lock:
                self.frame = f
                self.fid += 1

    def get(self):
        """Returns (frame, frame_id). The frame must be treated as read-only."""
        with self.lock:
            return self.frame, self.fid

    def stop(self):
        self.running = False
        self.thread.join(timeout=3)
        self.cap.release()
#=======================================================


#=======================================================
class Detector:
    """Runs inference on the newest frame in a background thread."""

    def __init__(self, stream, infer_fn):
        self.stream = stream
        self.infer_fn = infer_fn
        self.dets = []          # list of (x1, y1, x2, y2, cls, score)
        self.det_frame = None   # the exact frame the detections were computed on
        self.det_fid = 0        # frame id the detections belong to
        self.infer_ms = 0.0
        self.lock = threading.Lock()
        self.running = True
        self.thread = threading.Thread(target=self._run, daemon=True)
        self.thread.start()

    def _run(self):
        last_fid = 0
        while self.running:
            frame, fid = self.stream.get()
            if frame is None or fid == last_fid:
                time.sleep(0.002)
                continue
            last_fid = fid

            t0 = time.perf_counter()
            try:
                dets = self.infer_fn(frame)
            except Exception as e:
                print("Inference error:", e)
                time.sleep(0.5)
                continue
            ms = (time.perf_counter() - t0) * 1000.0

            with self.lock:
                self.dets = dets
                self.det_frame = frame
                self.det_fid = fid
                # smoothed so the on-screen figure is readable
                self.infer_ms = ms if self.infer_ms == 0 else (0.9 * self.infer_ms + 0.1 * ms)

    def get(self):
        """Returns (frame, frame_id, detections, infer_ms), all from the same inference."""
        with self.lock:
            return self.det_frame, self.det_fid, self.dets, self.infer_ms

    def stop(self):
        self.running = False
        self.thread.join(timeout=3)
#=======================================================


# =====================
# CONFIG
# =====================

# Check arguments and GUI before doing any expensive work
if (len(sys.argv) != 2):
    print("ERROR, USAGE: modeltest.py <CAMNAME>")
    sys.exit(1)

if gui_available() == False:
    print("ERROR, this program requires a GUI to operate. Copy it and the ./onnx directory to a Unix compatible system with a GUI")
    sys.exit(1)

camname = sys.argv[1]
HLS_URL = camname

mydir = os.path.abspath(os.path.dirname(__file__)) #gives dir without trailing /
modeldir = mydir + "/onnx"
target = "bbrelease-640x640Q.onnx"

IMG_SIZE = 640
CONF_THRES = 0.4
HIGHER_THRES = 0.75

classNames = {
    0: 'person',
    1: 'car',
    2: 'truck',
    3: 'motorcycle',
    4: 'van',
    5: 'bus',
    6: 'bicycle',
    7: 'selftest'
}

#Embedding the 7z password in the code like this is not meant to be secure its meant to force
#anyone stealing it to read the notice below so they do not inadvertently steal it without knowing
#what they are doing

# WARNING: The ONNX AI model is NOT under GPL. It is covered by the BB AI Model License.       #
# Do not use this password to extract it for use in non BigBrother software unless you         #
# have purchased a commercial license. See www.bigbrothercctv.org/BB-AIMODEL-LICENSE-1.1.txt   #
#                                                                    DO NOT STEAL   READ ^^^   #
with py7zr.SevenZipFile(modeldir + "/bbrelease.7z", mode="r", password="vR8!qL2#xN6@kT4$mP8&zW6^cH1*Ys5") as archive:

    print("Files inside the 7z archive:")
    print(archive.getnames())

    if hasattr(archive, "read"):
        # 0.22 and other older versions
        model_bytes = archive.read([target])[target].read()
    else:
        # 1.0+, where read() was removed
        factory = MemFactory()
        archive.extract(targets=[target], factory=factory)
        model_bytes = factory.files[target].getvalue()

# =====================
# LOAD MODEL (onnxruntime)
# =====================

sess, provider = tryONNXRuntimeHardware(model_bytes)

print("Was able to choose " + str(provider) + " as execution provider for neural network")

in_names = [i.name for i in sess.get_inputs()]
out_names = [o.name for o in sess.get_outputs()]
print("Model inputs:", in_names)

# Work out which kind of RT-DETR export this is:
#  - Official RT-DETRv2 export (lyuwenyu/RT-DETR export_onnx.py):
#      inputs  "images" (1,3,640,640) + "orig_target_sizes" (1,2 int64 [w,h])
#      outputs "labels", "boxes" (xyxy, original image pixels), "scores"
#  - Single-output export (e.g. Ultralytics style):
#      input   (1,3,640,640)
#      output  (1, num_queries, 4 + num_classes), boxes as cx,cy,w,h
#  - Raw export without postprocessor:
#      input   "images" (1,3,640,640)
#      outputs "pred_logits" (1, num_queries, num_classes) + "pred_boxes" (1, num_queries, 4 cx,cy,w,h 0-1)
OFFICIAL_EXPORT = {"labels", "boxes", "scores"}.issubset(set(out_names))
RAW_EXPORT = (not OFFICIAL_EXPORT) and len(out_names) == 2
print("Model outputs:", out_names)
if OFFICIAL_EXPORT:
    print("Detected format: official RT-DETRv2 (labels/boxes/scores)")
elif RAW_EXPORT:
    print("Detected format: raw RT-DETRv2 (pred_logits/pred_boxes)")
else:
    print("Detected format: single output tensor")


# =====================
# INFERENCE
# =====================
def run_inference(frame):
    """Returns a list of (x1, y1, x2, y2, cls, score) in frame pixel coordinates."""
    h, w = frame.shape[:2]

    # ---- PREPROCESS ----
    # Plain resize to 640x640, RGB, scaled 0-1 (no letterbox, no mean/std)
    blob = cv2.dnn.blobFromImage(
        frame, 1/255.0, (IMG_SIZE, IMG_SIZE),
        swapRB=True, crop=False
    )

    # ---- INFERENCE ----
    if OFFICIAL_EXPORT:
        feeds = {}
        for name in in_names:
            if "size" in name:
                feeds[name] = np.array([[w, h]], dtype=np.int64)
            else:
                feeds[name] = blob
        outs = dict(zip(out_names, sess.run(out_names, feeds)))

        det_labels = outs["labels"].reshape(-1).astype(int)
        det_boxes = outs["boxes"].reshape(-1, 4).astype(np.float32)  # already xyxy in frame pixels
        det_scores = outs["scores"].reshape(-1).astype(np.float32)
    elif RAW_EXPORT:
        a, b = sess.run(out_names, {in_names[0]: blob})
        # boxes are the output whose last dimension is 4
        if a.shape[-1] == 4:
            raw_boxes, logits = a, b
        else:
            logits, raw_boxes = a, b
        raw_boxes = raw_boxes.reshape(-1, 4)
        logits = logits.reshape(raw_boxes.shape[0], -1)

        probs = 1.0 / (1.0 + np.exp(-logits))   # sigmoid
        det_labels = probs.argmax(axis=1).astype(int)
        det_scores = probs.max(axis=1).astype(np.float32)

        cx, cy, bw, bh = raw_boxes[:, 0], raw_boxes[:, 1], raw_boxes[:, 2], raw_boxes[:, 3]
        det_boxes = np.stack([
            (cx - bw / 2) * w,
            (cy - bh / 2) * h,
            (cx + bw / 2) * w,
            (cy + bh / 2) * h
        ], axis=1)
    else:
        pred = sess.run(None, {in_names[0]: blob})[0]
        pred = pred.reshape(-1, pred.shape[-1])   # (num_queries, 4 + num_classes)

        cls_scores = pred[:, 4:]
        # If these look like raw logits rather than probabilities, apply a sigmoid
        if cls_scores.min() < 0.0 or cls_scores.max() > 1.0:
            cls_scores = 1.0 / (1.0 + np.exp(-cls_scores))
        det_labels = cls_scores.argmax(axis=1).astype(int)
        det_scores = cls_scores.max(axis=1).astype(np.float32)

        cx, cy, bw, bh = pred[:, 0], pred[:, 1], pred[:, 2], pred[:, 3]
        # Boxes are normally normalised 0-1; if they look like pixels, rescale from IMG_SIZE
        if pred[:, :4].max() > 2.0:
            sx, sy = w / IMG_SIZE, h / IMG_SIZE
        else:
            sx, sy = w, h
        det_boxes = np.stack([
            (cx - bw / 2) * sx,
            (cy - bh / 2) * sy,
            (cx + bw / 2) * sx,
            (cy + bh / 2) * sy
        ], axis=1)

    # ---- POSTPROCESS (no NMS needed for RT-DETR) ----
    keep = det_scores > CONF_THRES
    results = []

    for (x1, y1, x2, y2), cls, score in zip(det_boxes[keep], det_labels[keep], det_scores[keep]):
        x1 = int(max(0, min(w - 1, x1)))
        y1 = int(max(0, min(h - 1, y1)))
        x2 = int(max(0, min(w - 1, x2)))
        y2 = int(max(0, min(h - 1, y2)))

        if x2 <= x1 or y2 <= y1:
            continue  # degenerate box after clamping

        results.append((x1, y1, x2, y2, int(cls), float(score)))

    return results


# =====================
# DRAWING
# =====================
def draw_detections(frame, dets):
    for (x1, y1, x2, y2, cls, score) in dets:
        label = f"{classNames.get(cls, 'unknown')} {score:.2f}"

        if score >= HIGHER_THRES:
            colour = (0, 255, 0)
        else:
            colour = (0, 165, 255)

        cv2.rectangle(frame, (x1, y1), (x2, y2), colour, 2)

        # Get text size
        (text_width, text_height), baseline = cv2.getTextSize(label, cv2.FONT_HERSHEY_SIMPLEX, 0.5, 2)

        # Compute center of the bounding box
        center_x = x1 + (x2 - x1) // 2
        center_y = y1 + (y2 - y1) // 2

        # Compute bottom-left corner of text so it is centered
        text_x = center_x - text_width // 2
        text_y = center_y + text_height // 2

        cv2.putText(frame, label, (text_x, text_y), cv2.FONT_HERSHEY_SIMPLEX, 0.5, colour, 2)


# =====================
# OPEN STREAM AND RUN
# =====================
stream = LatestFrame(HLS_URL)
detector = Detector(stream, run_inference)

last_shown = 0

try:
    while True:
        # Show the exact frame the detections were computed on, so boxes always
        # line up with the picture. The display rate therefore equals the
        # inference rate, but there is never a mismatch.
        frame, fid, dets, infer_ms = detector.get()

        if frame is None or fid == last_shown:
            # No new result yet; keep the window responsive
            if cv2.waitKey(2) & 0xFF == 27:
                break
            continue
        last_shown = fid

        # Copy so we never draw on the buffer the reader/inference threads hold
        display = frame.copy()

        draw_detections(display, dets)

        cv2.putText(display, f"inference {infer_ms:.0f} ms", (10, 25),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 255, 255), 2)

        cv2.imshow("RT-DETRv2 OpenCV", display)

        if cv2.waitKey(1) & 0xFF == 27:
            break
finally:
    detector.stop()
    stream.stop()
    cv2.destroyAllWindows()
