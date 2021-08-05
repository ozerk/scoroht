##Container (startup process)
Application is containerized with Docker and can be started with docker-compose command:

```docker-compose up -d```
to run the script 
``` ./run.sh```

to connect to the container
```command docker exec -it scoro_php_1 /bin/bash```
to see all the commands. 
```php /application/scorocli help```

##CSV Files
``missed_categories.csv`` is the output of the script.
The comment is added as ```Wrong Category Details (OU)```
