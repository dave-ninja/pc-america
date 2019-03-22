# APP for PC-America Report

for Windows

step 1. install wampserver
step 2. php_pdo_sqlsrv_72_nts.dll, php_pdo_sqlsrv_72_ts.dll, php_sqlsrv_72_nts.dll, php_sqlsrv_72_ts.dll COPY in php.ini extension
step 3. extract pc-america.rar in ( localhost pc-america folder )
step 4. open http://localhost and create localhost ( pc-america.loc ) - virtual domain path (C:\wamp64\www\pc-america\public)
step 5. import xxx.xml to Task Schedule ( Windows )
step 6. open http://localhost/phpmyadmin and create mysql DB_DATABASE = ninja_pcamerica DB_PASSWORD=(empty) view in .env file on APP root
step 7. < https://john-dugan.com/add-php-windows-path-variable/ > PATH C:\wamp64\bin\php\php7.2.10
step 7. in pc-america folder open CMD and run command ( php artisan migrate )
step 8. http://pc-america.loc/
