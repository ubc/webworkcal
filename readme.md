WeBWorKCal
==================

A simple script to grab the future assignments and due dates to put them on to Google calendar for [WeBWorK](http://webwork.maa.org/).
Sometime the administrator would like to know when the assignments will due and how many students in the class. So that the administrator can prepare the server for the high load.
There might be some other use cases. Please let me know if you find it useful.

This script will create an event on the Google calendar and using student count, course name, assignment name as event summary and due date as the event start/end date. The summary looks like:

    STUDENT_COUNT-COURSE_NAME-ASSIGNMENT_NAME
If the event is already on the calendar, the script will try to update it if the due date or student count is different.

Requirements
------------
* WeBWorK database access
* [Google Calendar API](https://code.google.com/apis/console)
* A Google Calendar to put the events

Installation
-------------
1. Follow the instruction on Google API site to create a service account and download the private key
1. Create a new calendar or use existing one
1. Grant your service account read/write access to your Google calendar
    * Go to Google calendar and select the calendar you want to share with service account
    * Select "Share this Calendar"
    * Add your service account email address to person text field
    * Change the Permission Settings to "Make changes to events"
    * Click "Add Person"
1. Make a copy of config.ini.sample to config.ini
1. Change the values inside config.ini
1. Load the MySQL Prodcedure

        mysql -u USERNAME -p DATABASE_NAME < assignmentDueProcedure.sql
1. Run 

        php updateCalendar.php
1. Optionally, create a cron job for automatic update.


Developer Notes
--------------------
* The MySQL procedure uses dynamic table names and collecting data from those tables.
* The script uses Google Calendar API with service account.
* I didn't find a lot of documentations on those two topics, so this can be used as a reference.
