CLSA Equipment File Transfer
============================

Script used to transfer CLSA wearable device data from local computer to CLSA data warehouse.

Installation
------------

Make any necessary configration changes to config.php including changing TEST_ONLY and VERBOSE to false to setup for production.

Filenames
---------
Actigraph files are expected to be in the format, "<STUDY_ID> (YYYY-MM-DD).gt3x", where <STUDY_ID> should exist in the id_lookup.csv file, and the date, in YYYY-MM-DD format, will be used in the destination filename.
Ticwatch files are expected to be "<STUDY_ID>/<SERIAL_NUMBER>/<STUDY_ID>_<DATA_TYPE>_YYYYMMDD.m3d", where <STUDY_ID> should exist in the id_lookup.csv file, <SERIAL_NUMBER> and <DATA_TYPE> are ignored, and the date, in YYYYMMDD format, will be used to prevent older files from overwriting newer ones. 
