#!/usr/bin/env python3

####################################################################
#markup_y5onnx.py					                               #
#				                                                   #
#This is a test / debugging program to take a logged snapshot	      #
#as output by bbeventmonitor_mssd and re-run detection on it       #
#using same AI model, marking up the image with what it finds      #
#and the confidence and saving it as a new file                    #
#				                                                   #
#Usage: markup_y5onnx.py /inputimage.jpg /outputimage.jpg X.X      #
#where X.X is the minimum confidence threshold to apply to         #
#detections and should be set to the same as currently set	      #
#in bbeventmonitor_y5onnx		                                   #
#		                                                           #
#RT-DETRv2 version: runs the model with onnxruntime                #
#(Debian: apt install python3-onnxruntime). No NMS is needed,      #
#detections are simply filtered by score.                          #
#		                                                           #
####################################################################

# import libraries
import numpy as np
import cv2, os, sys
import py7zr
import onnxruntime as ort


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




if len(sys.argv)!=4:
    print("")
    print("Usage: "+str(sys.argv[0])+" /path/to/input.jpg /path/to/output.jpg confidence"  )
    print("Where confidence is a value between 0-1 e.g 0.5=50%. This should be set to same as used in bbeventmonitor_y5onnx. output path can be set to auto which will use same path as input with-out.jpg appended")
    print("This is a test / debugging program to take a logged snapshot (as output by bbeventmonitor_y5onnx and re-run detection on it) writing a marked up image to output showing detections")
    print("")
    exit(1)

inputpath=sys.argv[1]
outputpath=sys.argv[2]

if outputpath=="auto":
    outputpath=inputpath+"-out.jpg"

confidencemin=float(sys.argv[3])


BLUE   = (255,178,50)
BLACK   = (0,0,0)
WHITE   = (255,255,255)
FONT_FACE = cv2.FONT_HERSHEY_SIMPLEX
FONT_SCALE = 0.7
THICKNESS = 1

print("Using OpenCV "+cv2.__version__+", onnxruntime "+ort.__version__)

# load the network model
sess = ort.InferenceSession(model_bytes, providers=["CPUExecutionProvider"])
in_names = [i.name for i in sess.get_inputs()]
out_names = [o.name for o in sess.get_outputs()]

# Supported RT-DETR export formats:
#  - Official RT-DETRv2 export: inputs "images" + "orig_target_sizes" (int64 [w,h]),
#    outputs "labels", "boxes" (xyxy in original image pixels), "scores"
#  - Raw export: outputs "pred_logits" + "pred_boxes" (cx,cy,w,h normalised 0-1)
#  - Single output: (1, num_queries, 4 + num_classes), boxes cx,cy,w,h
OFFICIAL_EXPORT = {"labels", "boxes", "scores"}.issubset(set(out_names))
RAW_EXPORT = (not OFFICIAL_EXPORT) and len(out_names) == 2

# dictionary with the object class id and names on which the model is trained
classNames = { 0: 'person',1: 'car', 2: 'truck', 3: 'motorcycle', 4: 'van',5: 'bus',6: 'bicycle', 7: 'selftest'}


    
# Load the image
frame = cv2.imread(inputpath)
if frame is None:
    print("ERROR: could not read image "+inputpath)
    exit(1)
width = frame.shape[1] 
height = frame.shape[0]




# Plain resize to 640x640, RGB, scaled 0-1 (no letterbox, no mean/std)
blob = cv2.dnn.blobFromImage(frame, 1/255.0, (640, 640), swapRB=True,crop=False)

if OFFICIAL_EXPORT:
    feeds = {}
    for name in in_names:
        if "size" in name:
            feeds[name] = np.array([[width, height]], dtype=np.int64)
        else:
            feeds[name] = blob
    outs = dict(zip(out_names, sess.run(out_names, feeds)))
    det_labels = outs["labels"].reshape(-1).astype(int)
    det_boxes = outs["boxes"].reshape(-1, 4).astype(np.float32)  # already xyxy in image pixels
    det_scores = outs["scores"].reshape(-1).astype(np.float32)
else:
    if RAW_EXPORT:
        a, b = sess.run(out_names, {in_names[0]: blob})
        if a.shape[-1] == 4:
            raw_boxes, logits = a, b
        else:
            logits, raw_boxes = a, b
        raw_boxes = raw_boxes.reshape(-1, 4)
        probs = 1.0 / (1.0 + np.exp(-logits.reshape(raw_boxes.shape[0], -1)))   # sigmoid
        sx, sy = width, height
    else:
        pred = sess.run(None, {in_names[0]: blob})[0]
        pred = pred.reshape(-1, pred.shape[-1])
        raw_boxes, probs = pred[:, :4], pred[:, 4:]
        # Boxes are normally normalised 0-1; if they look like pixels, rescale from 640
        if raw_boxes.max() > 2.0:
            sx, sy = width / 640, height / 640
        else:
            sx, sy = width, height
    det_labels = probs.argmax(axis=1).astype(int)
    det_scores = probs.max(axis=1).astype(np.float32)
    cx, cy, w, h = raw_boxes[:, 0], raw_boxes[:, 1], raw_boxes[:, 2], raw_boxes[:, 3]
    det_boxes = np.stack([(cx - w/2) * sx, (cy - h/2) * sy, (cx + w/2) * sx, (cy + h/2) * sy], axis=1)

# RT-DETR needs no NMS - just keep detections at or above the confidence threshold
keep = det_scores >= confidencemin

for (x1, y1, x2, y2), class_id, confidence in zip(det_boxes[keep], det_labels[keep], det_scores[keep]):
    left = int(max(0, min(width - 1, x1)))
    top = int(max(0, min(height - 1, y1)))
    right = int(max(0, min(width - 1, x2)))
    bottom = int(max(0, min(height - 1, y2)))
    bwidth = right - left
    bheight = bottom - top
    
    
    cv2.rectangle(frame, (left, top), (left + bwidth, top + bheight), BLUE, 2)
        
        
    label = "{}:{:.2f}".format(classNames.get(int(class_id), 'unknown'), float(confidence))
    
    # Get text size
    (text_width, text_height), baseline = cv2.getTextSize(label,cv2.FONT_HERSHEY_SIMPLEX,0.5,2)

    # Compute center of the bounding box
    center_x = left + bwidth // 2
    center_y = top + bheight // 2

    # Compute bottom-left corner of text so it is centered
    text_x = center_x - text_width // 2
    text_y = center_y + text_height // 2

    
    cv2.putText(frame,label,(text_x, text_y),cv2.FONT_HERSHEY_SIMPLEX,0.5,BLUE,2)
    
    
    
  
    
    
    #Draw text onto image at location xy 
    #text_size = cv2.getTextSize(label, FONT_FACE, FONT_SCALE, THICKNESS)
    #dim, baseline = text_size[0], text_size[1]
    # Use text size to create a BLACK rectangle.
    #cv2.rectangle(frame, (left,top), (left + dim[0], top + dim[1] + baseline), BLUE, cv2.FILLED);
    # Display text inside the rectangle.
    #cv2.putText(frame, label, (left, top + dim[1]), FONT_FACE, FONT_SCALE, WHITE, THICKNESS, cv2.LINE_AA)
    
    
    
    
cv2.imwrite(outputpath, frame)
