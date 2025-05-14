USSD BLOG_SYSTEM

RegNo:-22RP03676
      -22RP02423

PHP USSD Application

This project is a USSD (Unstructured Supplementary Service Data) application built with PHP and MySQL. It handles user input via a USSD gateway and returns dynamic menu responses based on the user's session state.

 Features:

Dynamic USSD Menus – Navigates different functionalities using multi-level input.

View Latest Posts – Lists and reads detailed content of recent posts.

Submit a Post – Authors can create and publish new posts.

Register as Author – New authors can sign up using their name and email.

View Submitted Posts – Logged-in authors can view their own submissions.

View Profile – Shows the current author’s details.

Switch Author – Switch between existing author accounts by name (no password required).

Session Management – Tracks user input states and selections using a ussd_sessions table.

 Requirements:

- PHP 7.x or higher
- MySQL/MariaDB
- Web server (e.g., Apache)
- USSD Gateway (e.g., Africa's Talking) to post data to this script

Database schema:
Database Name:"blog_system1"
Tables:
 -admins                                                                              
 -posts                                
 -users                  
 -ussd_sessions 

Configure PHP Script:
$host = "localhost";
$user = "root";
$password = "";
$database = "blog_system1";

USSD Gateway Integration:
http://localhost:80/Ussd_Mini_Project/ussd.php

Local Testing:
"sessionId=123" 
"serviceCode=*384*40804#" 
"phoneNumber= 0789564312" 
"text=1*2"


Usernames and passwords:
 -Christine(Email:chris12@gmail.com, password:123)
 -Philemon(Email:Philemo12@gmail.com, password:123)
 -UWERA Carine(Email:ca@gmail.com, password:123)
   
   


   
   
