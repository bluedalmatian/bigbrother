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
#######################################################################################                

import cv2
import numpy as np
import sys
import py7zr
import os

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
with py7zr.SevenZipFile(modeldir + "/bbrelease-100-640x640.7z",mode="r",password="vR7!qL2#xN9@kT4$mP8&zW6^cH3*Ys5") as archive:

    print("Files inside the 7z archive:")
    print(archive.getnames())

    data = archive.read(["bbrelease-100-640x640.onnx"])

    model_bytes = data["bbrelease-100-640x640.onnx"].read()
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
IOU_THRES = 0.5

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
# LOAD MODEL (NO onnxruntime)
# =====================

net = cv2.dnn.readNetFromONNX(model_buffer)

# Optional acceleration
net.setPreferableBackend(cv2.dnn.DNN_BACKEND_OPENCV)
net.setPreferableTarget(cv2.dnn.DNN_TARGET_CPU)

# =====================
# OPEN STREAM
# =====================
cap = cv2.VideoCapture(HLS_URL, cv2.CAP_FFMPEG)

if not cap.isOpened():
    raise RuntimeError("Cannot open HLS stream")

# =====================
# MAIN LOOP
# =====================

frame_skip = 5
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
    blob = cv2.dnn.blobFromImage(
        frame, 1/255.0, (IMG_SIZE, IMG_SIZE),
        swapRB=True, crop=False
    )

    net.setInput(blob)
    outputs = net.forward()[0] 

    boxes = []
    scores = []
    class_ids = []

    # ---- POSTPROCESS ----
    for det in outputs:
        obj_conf = det[4]
        class_probs = det[5:]

        class_id = np.argmax(class_probs)
        class_score = class_probs[class_id]

        conf = obj_conf * class_score

        if conf > CONF_THRES:
            cx, cy, bw, bh = det[:4]

            x1 = int((cx - bw / 2) * w / IMG_SIZE)
            y1 = int((cy - bh / 2) * h / IMG_SIZE)
            x2 = int((cx + bw / 2) * w / IMG_SIZE)
            y2 = int((cy + bh / 2) * h / IMG_SIZE)

            boxes.append([x1, y1, x2 - x1, y2 - y1])
            scores.append(float(conf))
            class_ids.append(int(class_id))

    # ---- NMS ----
    indices = cv2.dnn.NMSBoxes(boxes, scores, CONF_THRES, IOU_THRES)

    for i in indices:
        i = i[0] if isinstance(i, (list, tuple, np.ndarray)) else i

        x, y, w_box, h_box = boxes[i]
        cls = class_ids[i]
        score = scores[i]

        label = f"{classNames.get(cls,'unknown')} {score:.2f}"

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
    cv2.imshow("YOLOv5 OpenCV HLS", frame)

    if cv2.waitKey(1) & 0xFF == 27:
        break

cap.release()
cv2.destroyAllWindows()
