#!/bin/bash
FILE="resources/views/admin/partials/sidebar.blade.php"
head -n 831 $FILE > scratch/part_a.blade.php
sed -n '832,1060p' $FILE > scratch/part_b.blade.php
sed -n '1061,1416p' $FILE > scratch/part_c.blade.php
tail -n +1417 $FILE > scratch/part_d.blade.php

cat scratch/part_a.blade.php scratch/part_c.blade.php scratch/part_b.blade.php scratch/part_d.blade.php > $FILE
