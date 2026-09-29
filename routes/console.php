<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('printlaris:push-printers')->everyMinute()->withoutOverlapping();
Schedule::command('printlaris:pull-config')->everyMinute()->withoutOverlapping();
