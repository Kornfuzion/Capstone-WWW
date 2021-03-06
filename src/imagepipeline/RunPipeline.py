#!/usr/bin/python
#! -*- encoding: utf-8 -*-
#
# usage : python RunPipeline.py event_id frame_id image_dir
#

import os
import subprocess
import sys
import time
from datetime import datetime
import shutil

import boto3
import json

from pyfcm import FCMNotification

def sendPushNotification(event_id, message):
    push_service = FCMNotification(api_key="AAAAkH7zsZQ:APA91bERB53BhlshsRLZ1p82VSih4d_GDs9eE9jdCaRiCFXhNls1_EZAmVBhNMyYF_iDANy6m9ReJ_PIfdoQlRsy5lM1wGI5V0EhuajBrmZeXDjPXlAwt2Olns3VnyTBny5UWjk3Uhdi")
    # Send a message to devices subscribed to a topic.
    result = push_service.notify_topic_subscribers(topic_name=event_id, message_body=message)

def receiveMessage():
    client = boto3.client('sqs')
    response = client.receive_message(
        QueueUrl='https://sqs.us-west-2.amazonaws.com/196517509005/modelProcessingQueue',
        AttributeNames=['All']
    )

    if ('Messages' in response): 
        event_message = response['Messages'][0]
        try:
            event_info = json.loads(event_message['Body'])
        except ValueError:
            #delete the message
            response = client.delete_message(
                QueueUrl='https://sqs.us-west-2.amazonaws.com/196517509005/modelProcessingQueue',
                ReceiptHandle=event_message['ReceiptHandle']
            )
            print("Improper body:" + event_message['Body'])
            exit(1)

        updateEvent(event_info['id'],"PROCESSING")
        
        #start measuring the time
        time_processed = datetime.now()
        start_time = time.time()

        #save where we were
        old_dir = os.getcwd()

        #run the pipe
        image_dir = createImageDirectory(event_info['id'], event_info['participants'])
        success = runPipeline(event_info['id'], event_info['frame'], image_dir)
        
        #clean up the temporary directory
        os.chdir(old_dir)
        shutil.rmtree(event_info['id']) 

        #save the time it took
        time_format = '%Y-%m-%d %H:%M:%S'
        end_time = time.time() 
        time_finished = datetime.now()
        db = boto3.client('dynamodb')
        result = db.update_item(
            TableName='events',
            ExpressionAttributeValues={
                ':time_processed': {
                    'S': time_processed.strftime(time_format),
                },
                ':time_finished': {
                    'S': time_finished.strftime(time_format),
                },
                ':process_time': {
                    'S': str(end_time - start_time),
                }, 
            },
            Key={
                'id': {
                    'S': event_info['id'],
                },
            },
            ReturnValues='ALL_NEW',
            UpdateExpression='SET process_time = :process_time, time_processed = :time_processed, time_finished = :time_finished',
        )

        #update the event 
        if (success):
            updateEvent(event_info['id'],"FINISHED")
            sendPushNotification(event_info['id'], "Your Scope is Ready!")
        else:
            updateEvent(event_info['id'],"FAILED")
            sendPushNotification(event_info['id'], "We didn't have enough info to create your Scope :(")

        response = client.delete_message(
            QueueUrl='https://sqs.us-west-2.amazonaws.com/196517509005/modelProcessingQueue',
            ReceiptHandle=event_message['ReceiptHandle']
        )


def runPipeline(event_id, frame_id, image_dir):
    BUNDLER_HOME = "/var/www/src/lib/bundler_sfm/"
    BUNDLER_BIN = os.path.join(BUNDLER_HOME, "bin")

    #move to the image directory and run this command 
    os.chdir(image_dir)

    #resize images if necessary
    #TODO: analyze each picture and determine landscape or portrait
    #assuming portrait pictures for now. 
    MAX_WIDTH=2000
    MAX_HEIGHT=4000
    #mogrify -resize widthxheight>
    pMogrify = subprocess.Popen( ["mogrify", "-resize", str(MAX_WIDTH) + "x" + str(MAX_HEIGHT) + ">", "*.jpg"] )
    pMogrify.wait()

    #should really just split it up according to the sh file to gain a higher level of control
    #/var/www/src/lib/bundler_sfm/RunBundler.sh
    pRunBundler = subprocess.Popen( [os.path.join(BUNDLER_HOME, "RunBundler.sh")] )
    pRunBundler.wait()
     
    #Bundle2PMVS <list.txt> <bundle.out> [pmvs_output_path (default: pmvs)]
    pBundler2PMVS = subprocess.Popen( [os.path.join(BUNDLER_BIN, "Bundle2PMVS"), "list.txt", "bundle/bundle.out"] )
    pBundler2PMVS.wait()

    pRadialUndistort = subprocess.Popen( [os.path.join(BUNDLER_BIN, "RadialUndistort"), "list.txt", "bundle/bundle.out", "pmvs"] )
    pRadialUndistort.wait()

    #Set up PMVS directory
    pmvs_text_dir = "pmvs/txt/"
    if not os.path.exists(pmvs_text_dir):
        os.mkdir(pmvs_text_dir)

    pmvs_visualize_dir = "pmvs/visualize/"
    if not os.path.exists(pmvs_visualize_dir):
        os.mkdir(pmvs_visualize_dir)

    pmvs_model_dir="pmvs/models/"
    if not os.path.exists(pmvs_model_dir):
        os.mkdir(pmvs_model_dir)

    image_list = list()

    for file in os.listdir("pmvs"):
        if file.endswith(".rd.jpg"):
            image_list.append(file)

    image_list.sort()
    image_num = 0
    for file in image_list:
        shutil.move(os.path.join("pmvs/", file), os.path.join(pmvs_visualize_dir, str(image_num).zfill(8) + ".jpg"))    
        shutil.move(os.path.join("pmvs/", str(image_num).zfill(8) + ".txt"), os.path.join(pmvs_text_dir, str(image_num).zfill(8) + ".txt"))
        image_num += 1

    pBundle2Vis = subprocess.Popen( [os.path.join(BUNDLER_BIN, "Bundle2Vis"), "pmvs/bundle.rd.out", "pmvs/vis.dat"] )
    pBundle2Vis.wait()

    #Run PMVS
    PMVS_BIN = "/var/www/src/lib/CMVS-PMVS/program/linux/main"

    pPMVS = subprocess.Popen( [os.path.join(PMVS_BIN, "pmvs2"), "pmvs/", "pmvs_options.txt", "PSET"] )
    pPMVS.wait()

    #pmvs

    #PLY color correction
    pSed = subprocess.Popen(["sed", "-i", "s/diffuse_//g", os.path.join(pmvs_model_dir, "pmvs_options.txt.ply")])
    pSed.wait()

    model_file_name = os.path.join(pmvs_model_dir, "pmvs_options.txt.ply") 
    
    #check if produced file has significant amount of data
    file_size = os.path.getsize(model_file_name)
    if (file_size <= 500): #picking 500 bytes for now. Realistically should be order of megabytes for most images
        return False

    #upload point cloud
    s3 = boto3.client('s3')
    with open(model_file_name, 'rb') as data:
        key = event_id + "/model/point/" + str(frame_id) + ".ply"
        s3.upload_fileobj(data, 'com.scope', key)

    POISSON_RECON_BIN = "/var/www/src/lib/PoissonRecon/Bin/Linux"

    #/var/www/src/lib/PoissonRecon/Bin/Linux/PoissonRecon --in 1.ply --out chair.trim.ply --depth 10 --density 
    pPoissonRecon = subprocess.Popen( [os.path.join(POISSON_RECON_BIN, "PoissonRecon"), "--in", model_file_name, "--out", model_file_name,"--depth", "10", "--color", "16", "--density"] )
    pPoissonRecon.wait()

    # use largest trim value that produces a significant amount of output
    mesh_file_name = os.path.join(pmvs_model_dir, "mesh.ply") 
    trim_val = 8;
    file_size = 0
    while (file_size <= 500): #picking 500 bytes for now. Realistically should be order of megabytes for most images
        pSurfaceTrim = subprocess.Popen( [os.path.join(POISSON_RECON_BIN, "SurfaceTrimmer"), "--in", model_file_name, "--out", mesh_file_name, "--trim", str(trim_val)] )
        pSurfaceTrim.wait()
        file_size = os.path.getsize(mesh_file_name) # check if trimmed file has significant amount of data
        trim_val = trim_val - 1; # decrement trim val

    #upload mesh cloud
    s3 = boto3.client('s3')
    with open(model_file_name, 'rb') as data:
        key = event_id + "/model/mesh/" + str(frame_id) + ".ply"
        s3.upload_fileobj(data, 'com.scope', key)

    return True

def createImageDirectory(event_id, participants):
    image_dir = os.path.join(str(event_id), "images")
    if not os.path.exists(image_dir):
        os.makedirs(image_dir)
    
    s3 = boto3.client('s3')
    for participant_id in participants:
        key = str(event_id) + "/images/" + str(participant_id) + ".jpg"
        response = s3.download_file('com.scope', key, key) #store in the local directory with the same structure
    return image_dir

def updateEvent(event_id, status):
    db = boto3.client('dynamodb')
    result = db.update_item(
        TableName='events',
        ExpressionAttributeNames={
            '#S': 'status'
        },
        ExpressionAttributeValues={
            ':status': {
                'S': status,
            }
        },
        Key={
            'id': {
                'S': event_id,
            },
        },
        ReturnValues='ALL_NEW',
        UpdateExpression='SET #S = :status',
    )

receiveMessage()
