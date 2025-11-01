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
#Requires OpenCV 4.10 or later                                     #
#		                                                           #
####################################################################

# import libraries
import numpy as np
import cv2, os, sys

mydir=os.path.abspath(os.path.dirname(__file__)) #gives dir without trailing /
modeldir=mydir+"/onnx"
classesPath=modeldir+"/classes.txt" #not currently used class names defined below
modelPath = modeldir+"/bbrelease-100-640x640.onnx"

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
scoremin=0.5#To filter low probability class scores where one object has been ID'd as > 1 class
nmsmin=0.45


BLUE   = (255,178,50)
BLACK   = (0,0,0)
WHITE   = (255,255,255)
FONT_FACE = cv2.FONT_HERSHEY_SIMPLEX
FONT_SCALE = 0.7
THICKNESS = 1

cvversion=cv2.__version__
cvversionelements=cvversion.split(".")


if (int(cvversionelements[0])<5) and (int(cvversionelements[1])<10):
    print("This program requires OpenCV version 4.10 or later, you are using "+cvversionelements[0]+"."+cvversionelements[1])
    exit(1)
else:
    print("Using OpenCV "+cvversionelements[0]+"."+cvversionelements[1])

# read a network model 
net = cv2.dnn.readNetFromONNX(modelPath)

# dictionary with the object class id and names on which the model is trained
classNames = { 0: 'person',1: 'car', 2: 'truck', 3: 'motorcycle', 4: 'van',5: 'bus',6: 'bicycle'}



#Use NPU########################################################
#net.setPreferableBackend(cv2.dnn.DNN_BACKEND_INFERENCE_ENGINE)
#net.setPreferableTarget(cv2.dnn.DNN_TARGET_NPU)
################################################################
    
# Load the image
frame = cv2.imread(inputpath)
width = frame.shape[1] 
height = frame.shape[0]




blob = cv2.dnn.blobFromImage(frame, 1/255.0, (640, 640), swapRB=True,crop=False)
net.setInput(blob)
outputs = net.forward(net.getUnconnectedOutLayersNames())
    
class_ids = []
confidences = []
boxes = []

rows = outputs[0].shape[1]
image_height, image_width = frame.shape[:2]   
x_factor = width / 640
y_factor =  height / 640

for r in range(rows):
    row = outputs[0][0][r]
    confidence = row[4]
    if confidence >= confidencemin:
        classes_scores = row[5:]
        class_id = np.argmax(classes_scores)
        if (classes_scores[class_id] > scoremin):
            confidences.append(confidence)
            class_ids.append(class_id)
            cx, cy, w, h = row[0], row[1], row[2], row[3]
            left = int((cx - w/2) * x_factor)
            top = int((cy - h/2) * y_factor)
            bwidth = int(w * x_factor)
            bheight = int(h * y_factor)
            box = np.array([left, top, bwidth, bheight])
            boxes.append(box)            

indices = cv2.dnn.NMSBoxes(boxes, confidences, confidencemin, nmsmin)
for i in indices:           
    box = boxes[i]
    left = box[0]
    top = box[1]
    bwidth = box[2]
    bheight = box[3]             
    cv2.rectangle(frame, (left, top), (left + bwidth, top + bheight), BLUE, 3*THICKNESS)
    label = "{}:{:.2f}".format(classNames[class_ids[i]], confidences[i])
    
    
    #Draw text onto image at location xy 
    text_size = cv2.getTextSize(label, FONT_FACE, FONT_SCALE, THICKNESS)
    dim, baseline = text_size[0], text_size[1]
    # Use text size to create a BLACK rectangle.
    cv2.rectangle(frame, (left,top), (left + dim[0], top + dim[1] + baseline), BLUE, cv2.FILLED);
    # Display text inside the rectangle.
    cv2.putText(frame, label, (left, top + dim[1]), FONT_FACE, FONT_SCALE, WHITE, THICKNESS, cv2.LINE_AA)
    
    
    
    
cv2.imwrite(outputpath, frame)
