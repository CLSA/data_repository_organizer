OVERVIEW
========

All data files will have a specific path on the filesystem:

<study> / <phase> / <category> / <name> / <uid> / <filename>

Where:
  study: The name of the study the data was recorded for (clsa, covid19_brain, etc)
  phase: bl for baseline, f1 for Follow-up 1, f2 for Follow-up 2, etc
         Note that if a study does not have multiple phases then bl will be the only directory
  category: The category of the data (like Opal table)
  name: The variable name of data (STP_DOTREC_DCS, RES_HIP_DICOM, Measure.SR, etc)
  uid: The participant's CLSA ID
  filename: The filename of the data (will include the study/phase/category/name/uid details, possibly more)


DEFINITIONS
===========
<PHASE>: The numeric phase of the study the data came from (eg: 1, 2, 3, etc)
<UID>: The participant identifier (eg: A123456)
<SIDE>: Either left or right
<N>: The zero-padded number of a repeated scan (eg: 1, 2, 3, etc)


DIRECTORIES
===========
.
├── temporary
│   ├── CAL (DAL, HAM, MAN, MCG, NUM, OTT, SFU, SHE, VIC)
│   │   ├── modality1
│   │   ├── modality2
│   │   └── etc...
│   └── nosite
│       ├── modality1
│       ├── modality2
│       └── etc...
├── invalid
│   ├── CAL (DAL, HAM, MAN, MCG, NUM, OTT, SFU, SHE, VIC)
│   │   ├── modality1
│   │   ├── modality2
│   │   └── etc...
│   └── nosite
│       ├── modality1
│       ├── modality2
│       └── etc...
├── cleaned
│   ├── CAL (DAL, HAM, MAN, MCG, NUM, OTT, SFU, SHE, VIC)
│   │   ├── modality1
│   │   ├── modality2
│   │   └── etc...
│   └── nosite
│       ├── modality1
│       ├── modality2
│       └── etc...
├── anonymized
│   └── clsa
│       ├── 1
│       │   └── dxa_hip
│       ├── 2
│       │   └── dxa_hip
│       └── 3
│           └── dxa_hip
├── supplementary
│   └── clsa
│       ├── 1
│       │   ├── dxa_forearm
│       │   └── ecg
│       ├── 2
│       │   ├── dxa_forearm
│       │   └── ecg
│       └── 3
│           ├── dxa_forearm
│           └── ecg
└── raw
    └── clsa
        ├── 1
        │   ├── choice_rt
        │   ├── audio
        │   ├── dxa_hip
        │   ├── dxa_forearm
        │   ├── dxa_lateral
        │   ├── dxa_spine
        │   ├── dxa_wbody
        │   ├── ecg
        │   ├── frax
        │   ├── retinal
        │   ├── spirometry
        │   ├── us_cineloop
        │   └── us_report
        ├── 2
        │   ├── choice_rt
        │   ├── audio
        │   ├── dxa_hip
        │   ├── dxa_forearm
        │   ├── dxa_lateral
        │   ├── dxa_spine
        │   ├── dxa_wbody
        │   ├── ecg
        │   ├── frax
        │   ├── retinal
        │   ├── spirometry
        │   ├── us_cineloop
        │   └── us_report
        └── 3
            ├── choice_rt
            ├── audio
            ├── dxa_hip
            ├── dxa_forearm
            ├── dxa_lateral
            ├── dxa_spine
            ├── dxa_wbody
            ├── ecg
            ├── frax
            ├── retinal
            ├── spirometry
            ├── us_cineloop
            └── us_report


OPAL AND CLSANFS DATA
=====================

CDTT
----
Opal BL: does not exist
Opal F1: does not exist
Opal F2: clsa-dcs-f2 / CDTT / RESULT_FILE
file-type: xlsx
path:

choice_rt
---------
Opal BL: clsa-dcs / CognitiveTest / RES_RESULT_FILE
Opal F1: clsa-dcs-f1 / CognitiveTest / RES_RESULT_FILE
Opal F2: clsa-dcs-f2 / CognitiveTest / RES_RESULT_FILE
file-type: xlsx (but actually csv files)
path: /raw/clsa/<PHASE>/choice_rt/<UID>/choice_rt.csv

ecg
---
Opal BL: clsa-dcs / ECG / RES_XML_FILE
Opal F1: clsa-dcs-f1 / ECG / RES_XML_FILE
Opal F2: clsa-dcs-f2 / ECG / RES_XML_FILE
file-type: xml
path: /raw/clsa/<PHASE>/ecg/<UID>/ecg.xml
path: /supplementary/clsa/<PHASE>/ecg/<UID>/ecg.xml (found in "sets", minor changes made)
path: /supplementary/clsa/<PHASE>/ecg/<UID>/ecg.jpeg (found in "sets", generated from xml data)

frax
----
Opal BL: does not exist
Opal F1: clsa-dcs-f1 / Frax / RES_RESULT_FILE
Opal F2: clsa-dcs-f2 / Frax / RES_RESULT_FILE
file-type: txt
path: /raw/clsa/<PHASE>/frax/<UID>/frax.txt

fas-a recording
---------------
Opal BL: clsa-dcs / NeuropsychologicalBattery / FAS_AREC_DCS
Opal F1: clsa-dcs-f1 / NeuropsychologicalBattery / FAS_AREC_COF1
Opal F2: clsa-dcs-f2 / StroopFAS / FAS_AREC_COF2
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/a_word_fluency.wav

fas-f recording
---------------
Opal BL: clsa-dcs / NeuropsychologicalBattery / FAS_FREC_DCS
Opal F1: clsa-dcs-f1 / NeuropsychologicalBattery / FAS_FREC_COF1
Opal F2: clsa-dcs-f2 / StroopFAS / FAS_FREC_COF2
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/f_word_fluency.wav

fas-s recording
---------------
Opal BL: clsa-dcs / NeuropsychologicalBattery / FAS_SREC_DCS
Opal F1: clsa-dcs-f1 / NeuropsychologicalBattery / FAS_SREC_COF1
Opal F2: clsa-dcs-f2 / StroopFAS / FAS_SREC_COF2
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/s_word_fluency.wav

stroop dot recording
--------------------
Opal BL: clsa-dcs / NeuropsychologicalBattery / STP_DOTREC_DCS
Opal F1: clsa-dcs-f1 / NeuropsychologicalBattery / STP_DOTREC_COF1
Opal F2: clsa-dcs-f2 / StroopFAS / STP_DOTREC_COF2
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/stroop_dot.wav

stroop word recording
---------------------
Opal BL: clsa-dcs / NeuropsychologicalBattery / STP_WORREC_DCS
Opal F1: clsa-dcs-f1 / NeuropsychologicalBattery / STP_WORREC_COF1
Opal F2: clsa-dcs-f2 / StroopFAS / STP_WORREC_COF2
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/stroop_word.wav

stroop colour recording
-----------------------
Opal BL: clsa-dcs / NeuropsychologicalBattery / STP_COLREC_DCS
Opal F1: clsa-dcs-f1 / NeuropsychologicalBattery / STP_COLREC_COF1
Opal F2: clsa-dcs-f2 / StroopFAS / STP_COLREC_COF2
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/stroop_colour.wav

spirometry flow
---------------
Opal BL: clsa-dcs / Spirometry / Measure.RES_FLOW_VALUES (repeated)
Opal F1: clsa-dcs-f1 / Spirometry / Measure.RES_FLOW_VALUES (repeated)
Opal F2: clsa-dcs-f2 / Spirometry / Measure.RES_FLOW_VALUES (repeated)
file-type: txt
path: /raw/clsa/<PHASE>/spirometry/<UID>/spirometry-flow_trial_<T>_rank_<R>.txt
notes: "trial_N_rank_N" indicated by Measure.OUTPUT_TRIAL_RANK

spirometry volume
-----------------
Opal BL: clsa-dcs / Spirometry / Measure.RES_VOLUME_VALUES (repeated)
Opal F1: clsa-dcs-f1 / Spirometry / Measure.RES_VOLUME_VALUES (repeated)
Opal F2: clsa-dcs-f2 / Spirometry / Measure.RES_VOLUME_VALUES (repeated)
file-type: txt
path: /raw/clsa/<PHASE>/spirometry/<UID>/spirometry-volume_trial_<T>_rank_<R>.txt
notes: "trial_N_rank_N" indicated by Measure.OUTPUT_TRIAL_RANK

spirometry report
-----------------
Opal BL: does not exist
Opal F1: clsa-dcs-f1 / Spirometry / Measure.RES_REPORT (repeated)
Opal F2: clsa-dcs-f2 / Spirometry / Measure.RES_REPORT (repeated)
file-type: pdf
path: /raw/clsa/<PHASE>/spirometry/<UID>/spirometry-report.pdf
notes: not actually repeated

us cineloop [123]
-----------------
Opal BL: clsa-dcs-images / CarotidIntima / Measure.CINELOOP_[123] (repeated)
Opal F1: clsa-dcs-images-f1 / CarotidIntima / Measure.CINELOOP_1 (repeated)
Opal F2: clsa-dcs-images-f2 / CarotidIntima / Measure.CINELOOP_1 (repeated)
file-type: gz -> dcm
path: /raw/clsa/<PHASE>/us_cineloop/<UID>/us_cineloop-[123]_<SIDE>_<N>.dcm
notes: either left or right as indicated by Measure.SIDE; "[123]_" for BL only

us plaque cineloop
------------------
Opal BL: clsa-dcs-images / Plaque / Measure.CINELOOP_1 (repeated)
Opal F1: does not exist
Opal F2: does not exist
file-type: gz -> dcm
path: /raw/clsa/<PHASE>/us_plaque_cineloop/<UID>/us_plaque_cineloop-<SIDE>_<N>.dcm
notes: either left or right as indicated by Measure.SIDE

us structured report
--------------------
Opal BL: clsa-dcs-images / CarotidIntima / Measure.SR (repeated)
Opal F1: clsa-dcs-images-f1 / CarotidIntima / Measure.SR_1 (repeated)
Opal F2: clsa-dcs-images-f2 / CarotidIntima / Measure.SR_1 (repeated)
file-type: gz -> dcm
path: /raw/clsa/<PHASE>/us_report/<UID>/us_report-<SIDE>.dcm
notes: either left or right as indicated by Measure.SIDE; not actually repeated

us still image [123]
--------------------
Opal BL: clsa-dcs-images / CarotidIntima / Measure.STILL_IMAGE (repeated)
Opal F1: clsa-dcs-images-f1 / CarotidIntima / Measure.STILL_IMAGE_[123] (repeated)
Opal F2: clsa-dcs-images-f2 / CarotidIntima / Measure.STILL_IMAGE_[123] (repeated)
file-type: gz -> dcm
path: /raw/clsa/<PHASE>/us_report/<UID>/us_still-[123]_<SIDE>_<N>.dcm
notes: either left or right as indicated by Measure.SIDE

dxa dual hip
------------
Opal BL: clsa-dcs-images / DualHipBoneDensity / Measure.RES_HIP_DICOM (repeated)
Opal F1: clsa-dcs-images-f1 / DualHipBoneDensity / Measure.RES_HIP_DICOM (repeated)
Opal F2: clsa-dcs-images-f2 / DualHipBoneDensity / Measure.RES_HIP_DICOM (repeated)
file-type: dicom
path: /raw/clsa/<PHASE>/dxa_hip/<UID>/dxa_hip-<SIDE>_<N>.dcm
path: /supplementary/clsa/<PHASE>/dxa_hip/<UID>/dxa_hip_reanalyzed-<SIDE>_<N>.dcm
path: /supplementary/clsa/<PHASE>/dxa_hip/<UID>/dxa_hip-<SIDE>_<N>.jpeg
notes: either left or right as indicated by Measure.OUTPUT_HIP_SIDE

dxa forearm
-----------
Opal BL: clsa-dcs-images / ForearmBoneDensity / RES_FA_DICOM
Opal F1: clsa-dcs-images-f1 / ForearmBoneDensity / RES_FA_DICOM
Opal F2: clsa-dcs-images-f2 / ForearmBoneDensity / RES_FA_DICOM
file-type: dicom
path: /raw/clsa/<PHASE>/dxa_forearm/<UID>/dxa_forearm_<SIDE>.dcm (SIDE defined by INPUT_FA_SIDE)
path: /supplementary/ ... _reanalyzed.dcm
path: /supplementary/clsa/<PHASE>/dxa_forearm/<UID>/dxa_forearm.jpeg (manually generated from dicom files using script)

dxa hip
-------
Opal BL: clsa-dcs-images / HipBoneDensity / RES_HIP_DICOM
Opal F1: clsa-dcs-images-f1 / HipBoneDensity / RES_HIP_DICOM
Opal F2: clsa-dcs-images-f2 / HipBoneDensity / RES_HIP_DICOM
file-type: dicom
path: N/A
notes: Data not recorded, can be ignored

dxa lateral measure
-------------------
Opal BL: clsa-dcs-images / LateralBoneDensity / RES_SEL_DICOM_MEASURE
Opal F1: clsa-dcs-images-f1 / LateralBoneDensity / RES_SEL_DICOM_MEASURE
Opal F2: clsa-dcs-images-f2 / LateralBoneDensity / RES_SEL_DICOM_MEASURE
file-type: dicom
path: /raw/clsa/<PHASE>/dxa_lateral/<UID>/dxa_lateral.dcm
path: /supplementary/ ... .jpeg

dxa lateral ot
--------------
Opal BL: clsa-dcs-images / LateralBoneDensity / RES_SEL_DICOM_OT
Opal F1: clsa-dcs-images-f1 / LateralBoneDensity / RES_SEL_DICOM_OT
Opal F2: clsa-dcs-images-f2 / LateralBoneDensity / RES_SEL_DICOM_OT
file-type: dicom
path: /raw/clsa/<PHASE>/dxa_lateral/<UID>/dxa_lateral_ot.dcm
notes: ot is "quantitative morphometry" but all data is empty (was not recorded)

dxa lateral pr
--------------
Opal BL: clsa-dcs-images / LateralBoneDensity / RES_SEL_DICOM_PR
Opal F1: clsa-dcs-images-f1 / LateralBoneDensity / RES_SEL_DICOM_PR
Opal F2: clsa-dcs-images-f2 / LateralBoneDensity / RES_SEL_DICOM_PR
file-type: dicom
path: /raw/clsa/<PHASE>/dxa_lateral/<UID>/dxa_lateral_pr.dcm
notes: pr is "structured report file for vertebral markers"

dxa spine
---------
Opal BL: does not exist
Opal F1: does not exist
Opal F2: clsa-dcs-images-f2 / SpineBoneDensity / RES_SP_DICOM
file-type: dicom
path: /raw/clsa/<PHASE>/dxa_spine/<UID>/dxa_spine.dcm
path: /supplementary/ ... _reanalyzed.dcm
path: /supplementary/ ... .jpeg

dxa whole body 1
----------------
Opal BL: clsa-dcs-images / WholeBodyBoneDensity / RES_WB_DICOM_1
Opal F1: clsa-dcs-images-f1 / WholeBodyBoneDensity / RES_WB_DICOM_1
Opal F2: clsa-dcs-images-f2 / WholeBodyBoneDensity / RES_WB_DICOM_1
file-type: dicom
path: /raw/clsa/<PHASE>/dxa_wbody/<UID>/dxa_wbody.dcm
path: /supplementary/ ... _reanalyzed.dcm
path: /supplementary/ ... .jpeg

dxa whole body 2
----------------
Opal BL: clsa-dcs-images / WholeBodyBoneDensity / RES_WB_DICOM_2
Opal F1: clsa-dcs-images-f1 / WholeBodyBoneDensity / RES_WB_DICOM_2
Opal F2: clsa-dcs-images-f2 / WholeBodyBoneDensity / RES_WB_DICOM_2
file-type: dicom
path: /raw/clsa/<PHASE>/dxa_wbody/<UID>/dxa_wbody_bca.dcm
path: /supplementary/ ... _reanalyzed.dcm
path: /supplementary/ ... .jpeg

retinal
-------
Opal BL: clsa-dcs-images / RetinalScan / Measure.EYE (repeated)
Opal F1: does not exist
Opal F2: does not exist
file-type: jpeg
path: /raw/clsa/<PHASE>/retinal/<UID>/retinal_<SIDE>_<N>.jpeg (SIDE defined by Measure.SIDE)
notes: files need to be numbered since some might have multiple images per side

retinal left
------------
Opal BL: does not exist
Opal F1: clsa-dcs-images-f2 / RetinalScanLeft / EYE
Opal F2: clsa-dcs-images-f2 / RetinalScanLeft / EYE
file-type: jpeg
path: /raw/clsa/<PHASE>/retinal/<UID>/retinal_<SIDE>.dcm (SIDE defined by SIDE)
notes: figure out what the side variable is (so that opal points to the correct file)

retinal right
-------------
Opal BL: does not exist
Opal F1: clsa-dcs-images-f2 / RetinalScanRight / EYE
Opal F2: clsa-dcs-images-f2 / RetinalScanRight / EYE
file-type: jpeg
path: /raw/clsa/<PHASE>/retinal/<UID>/retinal_<SIDE>.dcm (SIDE defined by SIDE)
notes: figure out what the side variable is (so that opal points to the correct file)

cog uncategorized
-----------------
Opal BL: does not exist
Opal F1: does not exist
Opal F2: does not exist
clsanfs BL: /data/tracking/sabretooth_bl-live/UID/<NN>-out.wav
clsanfs BL: /data/tracking/sabretooth_bl-live/UID/<NN>-in.wav (operator)
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/<NN>-out.wav
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/<NN>-in.wav (operator)
clsanfs F2: does not exist
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/<NN>.wav
path: /raw/clsa/<PHASE>/audio/<UID>/<NN>-operator.wav

cog alphabet
------------
Opal BL: clsa-inhome / InHome_2 / COG_ALPTME_REC2_COM
Opal F1: clsa-inhome / InHome_2 / COG_ALPTME_REC2_COF1
Opal F2: clsa-inhome-f2 / InHome_2 / COG_ALPTME_REC2_COF2
clsanfs BL: not available (files are not categorized)
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/Alphabet-out.wav
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/Alphabet-in.wav (operator)
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/Alphabet-out.wav
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/Alphabet-in.wav (operator)
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/alphabet.wav
path: /raw/clsa/<PHASE>/audio/<UID>/alphabet-operator.wav

cog mental alternation
----------------------
Opal BL: clsa-inhome / InHome_2 / COG_ALTTME_REC_COM
Opal F1: clsa-inhome / InHome_2 / COG_ALTTME_REC_COF1
Opal F2: clsa-inhome-f2 / InHome_2 / COG_ALTTME_REC_COF2
clsanfs BL: not available (files are not categorized)
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/MAT Alternation-out.wav
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/MAT Alternation-in.wav (operator)
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/MAT Alternation-out.wav
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/MAT Alternation-in.wav (operator)
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/mental_alternation.wav
path: /raw/clsa/<PHASE>/audio/<UID>/mental_alternation-operator.wav

cog animal list
---------------
Opal BL: clsa-inhome / InHome_2 / COG_ANMLLLIST_REC_COM
Opal F1: clsa-inhome / InHome_2 / COG_ANMLLLIST_REC_COF1
Opal F2: clsa-inhome-f2 / InHome_2 / COG_ANMLLLIST_REC_COF2
clsanfs BL: not available (files are not categorized)
clsanfs F1: /data/tracking/sabretooth_f1-live/<UID>/Animal List-out.wav
clsanfs F1: /data/tracking/sabretooth_f1-live/<UID>/Animal List-in.wav (operator)
clsanfs F2: /data/tracking/sabretooth_f2-live/<UID>/Animal List-out.wav
clsanfs F2: /data/tracking/sabretooth_f2-live/<UID>/Animal List-in.wav (operator)
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/animal_fluency.wav
path: /raw/clsa/<PHASE>/audio/<UID>/animal_fluency-operator.wav

cog counting
------------
Opal BL: clsa-inhome / InHome_2 / COG_CNTTMEREC_COM
Opal F1: clsa-inhome / InHome_2 / COG_CNTTMEREC_COF1
Opal F2: clsa-inhome-f2 / InHome_2 / COG_CNTTMEREC_COF2
clsanfs BL: not available (files are not categorized)
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/COUNTING to 20-out.wav
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/COUNTING to 20-in.wav (operator)
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/COUNTING to 20-out.wav
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/COUNTING to 20-in.wav (operator)
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/counting.wav
path: /raw/clsa/<PHASE>/audio/<UID>/counting-operator.wav

cog delayed word list
---------------------
Opal BL: clsa-inhome / InHome_2 / COG_WRDLST2_REC_COM
Opal F1: clsa-inhome / InHome_2 / COG_WRDLST2_REC_COF1
Opal F2: clsa-inhome-f2 / InHome_2 / COG_WRDLST2_REC_COF2
clsanfs BL: not available (files are not categorized)
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/REY II-out.wav
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/REY II-in.wav (operator)
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/REY II-out.wav
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/REY II-in.wav (operator)
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/delayed_word_list.wav
path: /raw/clsa/<PHASE>/audio/<UID>/delayed_word_list-operator.wav

cog immediate word list
-----------------------
Opal BL: clsa-inhome / InHome_2 / COG_WRDLSTREC_COM
Opal F1: clsa-inhome / InHome_2 / COG_WRDLSTREC_COF1
Opal F2: clsa-inhome-f2 / InHome_2 / COG_WRDLSTREC_COF2
clsanfs BL: not available (files are not categorized)
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/REY I-out.wav
clsanfs F1: /data/tracking/sabretooth_f1-live/UID/REY I-in.wav (operator)
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/REY I-out.wav
clsanfs F2: /data/tracking/sabretooth_f2-live/UID/REY I-in.wav (operator)
file-type: wav
path: /raw/clsa/<PHASE>/audio/<UID>/immediate_word_list.wav
path: /raw/clsa/<PHASE>/audio/<UID>/immediate_word_list-operator.wav

dxa hip recovery left
---------------------
Opal BL: clsa-dcs-images / HipRecoveryLeft / RES_HIP_DICOM
Opal F1: clsa-dcs-images-f1 / HipRecoveryLeft / RES_HIP_DICOM
Opal F2: does not exist
file-type: dicom
path:
notes: recovery dxa data is to be reviewed by Dean

dxa hip recovery right
----------------------
Opal BL: clsa-dcs-images / HipRecoveryRight / RES_HIP_DICOM
Opal F1: clsa-dcs-images-f1 / HipRecoveryRight / RES_HIP_DICOM
Opal F2: does not exist
file-type: dicom
path:
notes: recovery dxa data is to be reviewed by Dean

dxa lateral recovery
--------------------
Opal BL: clsa-dcs-images / LateralRecovery / RES_SEL_DICOM_MEASURE
Opal F1: clsa-dcs-images-f1 / LateralRecovery / RES_SEL_DICOM_MEASURE
Opal F2: does not exist
file-type: dicom
path:
notes: recovery dxa data is to be reviewed by Dean

dxa whole body recovery
-----------------------
Opal BL: clsa-dcs-images / WbodyRecovery / RES_WB_DICOM_1
Opal F1: clsa-dcs-images-f1 / WbodyRecovery / RES_WB_DICOM_1
Opal F2: does not exist
file-type: dicom
path:
notes: recovery dxa data is to be reviewed by Dean

actigraph
---------
file-type: gt3x
path: /raw/clsa/<PHASE>/actigraph/<UID>/<date>.gt3x
notes: all dates are in YYYYMMDD format

ticwatch
--------
file-type: multiple
path: /raw/clsa/<PHASE>/ticwatch/<UID>/StartupSettings.json
path: /raw/clsa/<PHASE>/ticwatch/<UID>/StudySettings.json
path: /raw/clsa/<PHASE>/ticwatch/<UID>/<date>_log.csv (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/Accelerometer_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/Activity_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/ActivityTransition_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/AmbientLight_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/ChargeLevel_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/GnssStatus_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/GnssStatusSummaries_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/Gyroscope_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/HeartRate_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/Location-Encrypted_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/OnBody_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/PowerStates_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/StepCounter_<date>.m3d (multiple dates)
path: /raw/clsa/<PHASE>/ticwatch/<UID>/StepDetector_<date>.m3d (multiple dates)
notes: all dates are in YYYYMMDD format
