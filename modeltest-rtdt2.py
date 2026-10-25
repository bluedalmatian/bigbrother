#######################################################################################
# modeltest.py                                                                        #
# Copyright Andrew Wood 2026                                                          #
# Licensed under GNU Public License 3                                                 #
#                                                                                     #
# Run the model against the specified video stream                                    #
# to display live view of what it can detect                                          #
#                                                                                     #
# Adjust CONF_THRES to lower threshold and HIGHER_THRES to higher threshold           #
#                                                                                     #
# RT-DETRv2 version: no objectness, no NMS - the model outputs a fixed set of         #
# queries (typically 300) that are simply filtered by score.                          #
#                                                                                     #
#######################################################################################

import cv2
import numpy as np
import sys
import py7zr
import os
import onnxruntime as ort


#=======================================================
def tryONNXRuntimeHardware(modelbytes):  
    available = ort.get_available_providers()
    candidates = []
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

    session = None

    for providers in candidates:
        try:
            print("Trying to run:",providers)
            session = ort.InferenceSession(modelbytes,providers=providers)
            print("Using:", providers)
            return providers
        except Exception as e:
            print("Failed to run:", providers, e)

    if session is None:
        print("Could not create ONNX Runtime session")
        exit()


#=======================================================



#========================================================
def gui_available():
    try:
        cv2.namedWindow("__test__", cv2.WINDOW_NORMAL)
        cv2.destroyWindow("__test__")
        return True
    except cv2.error:
        return False
#=======================================================

# =====================
# CONFIG
# =====================

mydir=os.path.abspath(os.path.dirname(__file__)) #gives dir without trailing /
modeldir=mydir+"/onnx"

#Embedding the 7z password in the code like this is not meant to be secure its meant to force
#anyone stealing it to read the notice below so they do not inadvertently steal it without knowing
#what they are doing

# WARNING: The ONNX AI model is NOT under GPL. It is covered by the BB AI Model License.       #
# Do not use this password to extract it for use in non BigBrother software unless you         #
# have purchased a commercial license. See www.bigbrothercctv.org/BB-AIMODEL-LICENSE-1.1.txt   #
#                                                                    DO NOT STEAL   READ ^^^   # 
with py7zr.SevenZipFile(modeldir + "/bbrelease.7z",mode="r",password="vR8!qL2#xN6@kT4$mP8&zW6^cH1*Ys5") as archive:

    print("Files inside the 7z archive:")
    print(archive.getnames())

    data = archive.read(["bbrelease-640x640Q.onnx"])

    model_bytes = data["bbrelease-640x640Q.onnx"].read()
    model_buffer = np.frombuffer(model_bytes, dtype=np.uint8)


if (len(sys.argv)!=2):
	print("ERROR, USAGE: modeltest.py <CAMNAME>")
	sys.exit()
	
if gui_available()==False:
    print("ERROR, this program requires a GUI to operate. Copy it and the ./onnx directory to a Unix compatible system with a GUI")
    sys.exit()
	
camname=sys.argv[1]
HLS_URL = camname

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

# =====================
# LOAD MODEL (onnxruntime)
# =====================



provider=tryONNXRuntimeHardware(model_bytes)

print("Was able to choose "+str(provider)+" as execution provider for neural network")

sess = ort.InferenceSession(model_bytes, providers=provider)

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
# OPEN STREAM
# =====================
cap = cv2.VideoCapture(HLS_URL, cv2.CAP_FFMPEG)

if not cap.isOpened():
    raise RuntimeError("Cannot open HLS stream")

# =====================
# MAIN LOOP
# =====================

frame_skip = 10
frame_count = 0

while True:
    ret, frame = cap.read()

    if not ret:
        print("Reconnecting stream...")
        cap.release()
        cap = cv2.VideoCapture(HLS_URL, cv2.CAP_FFMPEG)
        continue

    frame_count += 1

    if frame_count % frame_skip != 0:
        continue

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

    for (x1, y1, x2, y2), cls, score in zip(det_boxes[keep], det_labels[keep], det_scores[keep]):
        x1 = int(max(0, min(w - 1, x1)))
        y1 = int(max(0, min(h - 1, y1)))
        x2 = int(max(0, min(w - 1, x2)))
        y2 = int(max(0, min(h - 1, y2)))

        x, y = x1, y1
        w_box, h_box = x2 - x1, y2 - y1
        score = float(score)

        label = f"{classNames.get(int(cls),'unknown')} {score:.2f}"

        if score >= HIGHER_THRES:
            colour=(0,255,0)
        else:
            colour=(0, 165, 255)

        cv2.rectangle(frame, (x, y), (x + w_box, y + h_box), colour, 2)
        
        # Get text size
        (text_width, text_height), baseline = cv2.getTextSize(label,cv2.FONT_HERSHEY_SIMPLEX,0.5,2)

        # Compute center of the bounding box
        center_x = x + w_box // 2
        center_y = y + h_box // 2

        # Compute bottom-left corner of text so it is centered
        text_x = center_x - text_width // 2
        text_y = center_y + text_height // 2

        cv2.putText(frame,label,(text_x, text_y),cv2.FONT_HERSHEY_SIMPLEX,0.5,colour,2)

    cv2.imshow("RT-DETRv2 OpenCV HLS", frame)

    if cv2.waitKey(1) & 0xFF == 27:
        break

cap.release()
cv2.destroyAllWindows()
