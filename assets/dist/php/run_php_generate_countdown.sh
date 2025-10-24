#!/bin/bash
while true; do
    #Iterate over each line in jobs.txt
    while IFS= read -r line; do

    # command in commandline: php % cat jobs.txt | xargs -P 10 -n 1 php generate-one.php
    # redo in a multithreaded bash script
    php generate-one.php "$line" &
    #Limit to 10 parallel processes
    while (( $(jobs -r | wc -l) >= 10 )); do
        sleep 1
    done
    done < jobs.txt
    wait
    echo "Processo deu crash. Reiniciando em 5 segundos..."
    sleep 5
done