<?php

session_start();

if(!isset($_SESSION['user']))
{
    header("Location: login.php");
    exit;
}

require 'config/db.php';

$stmt = $conn->query("
SELECT *
FROM parameter_master_princek2
WHERE displayindashboard = 1
ORDER BY priority
LIMIT 12
");

include 'includes/header.php';
include 'includes/sidebar.php';

?>

<div class="content">

   <div class="top-header">

    <h2>⚡ Energy Dashboard</h2>

    <div>

        <button
        class="btn btn-dark me-2"
        onclick="toggleTheme()">
        Dark Mode
        </button>

        <a
        href="logout.php"
        class="btn btn-danger">
        Logout
        </a>

    </div>

</div
       

    <!-- KPI Cards -->

    <div class="row">

        <?php foreach($stmt as $row){ ?>

        <div class="col-md-3 mb-3">

            <div class="card shadow">

                <div class="card-body">

                    <div>
                        <?= $row['parameter_name']; ?>
                    </div>

                    <div
                        class="metric-value"
                        id="p<?= preg_replace('/[^A-Za-z0-9]/','',$row['parameter_name']); ?>"
                    >
                        0
                    </div>

                </div>

            </div>

        </div>

        <?php } ?>

    </div>

    <!-- KW Trend -->

    <div class="card shadow mb-4">

        <div class="card-body">

            <h5>📈 KW Trend</h5>

            <canvas id="kwChart"></canvas>

        </div>

    </div>

    <!-- Voltage Trend -->

    <div class="card shadow mb-4">

        <div class="card-body">

            <h5>⚡ Voltage Trend (Vr / Vy / Vb)</h5>

            <canvas id="voltageChart"></canvas>

        </div>

    </div>

    <!-- Current Trend -->

    <div class="card shadow mb-4">

        <div class="card-body">

            <h5>🔌 Current Trend (Ir / Iy / Ib)</h5>

            <canvas id="currentChart"></canvas>

        </div>

    </div>

    <!-- EB vs DG Pie Chart -->

    <div class="card shadow mb-4">

        <div class="card-body">

            <h5>📊 EB vs DG Energy</h5>

            <canvas id="pieChart"></canvas>

        </div>

    </div>

    <!-- Active Alarms -->

    <div class="card shadow">

        <div class="card-body">

            <h5 class="text-danger">
                🚨 Active Alarms
            </h5>

            <div id="alarmContainer">

                Loading alarms...

            </div>

        </div>

    </div>

</div>

<!-- Live KPI Values -->

<script>

function toggleTheme()
{
    document.body.classList.toggle('dark');
}

function loadData()
{
    fetch('api/getLiveData.php')
    .then(response => response.json())
    .then(data => {

        data.forEach(item => {

            let key = item.parameter_name
                .replace(/ /g,'')
                .replace(/\./g,'')
                .replace(/-/g,'');

            let box =
            document.getElementById(
                'p' + key
            );

            if(box)
            {
                box.innerHTML = item.value;
            }

        });

    });
}

loadData();

setInterval(loadData,5000);

</script>

<!-- KW Chart -->

<script>

new Chart(
document.getElementById('kwChart'),
{
    type:'line',

    data:{
        labels:[
            '10:00',
            '10:05',
            '10:10',
            '10:15',
            '10:20'
        ],

        datasets:[{

            label:'KW',

            borderColor:'blue',

            data:[
                100,
                120,
                130,
                115,
                140
            ],

            tension:0.4

        }]
    }
});

</script>

<!-- Voltage Chart -->

<script>

fetch('api/voltagedata.php')

.then(response => response.json())

.then(data => {

    new Chart(
        document.getElementById('voltageChart'),
        {
            type:'line',

            data:{

                labels:['1','2','3','4','5'],

                datasets:[

                {
                    label:'Vr',
                    data:data.Vr,
                    borderColor:'red',
                    tension:0.4
                },

                {
                    label:'Vy',
                    data:data.Vy,
                    borderColor:'blue',
                    tension:0.4
                },

                {
                    label:'Vb',
                    data:data.Vb,
                    borderColor:'green',
                    tension:0.4
                }

                ]
            }
        }
    );

});

</script>

<!-- Current Chart -->

<script>

fetch('api/currentdata.php')

.then(response => response.json())

.then(data => {

    new Chart(
        document.getElementById('currentChart'),
        {

            type:'line',

            data:{

                labels:['1','2','3','4','5'],

                datasets:[

                {
                    label:'Ir',
                    data:data.Ir,
                    borderColor:'red',
                    tension:0.4
                },

                {
                    label:'Iy',
                    data:data.Iy,
                    borderColor:'blue',
                    tension:0.4
                },

                {
                    label:'Ib',
                    data:data.Ib,
                    borderColor:'green',
                    tension:0.4
                }

                ]
            }

        }
    );

});

</script>

<!-- EB vs DG Pie Chart -->

<script>

fetch('api/piedata.php')

.then(response => response.json())

.then(data => {

    new Chart(
        document.getElementById('pieChart'),
        {

            type:'pie',

            data:{

                labels:[
                    'EB Energy',
                    'DG Energy'
                ],

                datasets:[{

                    data:[
                        data.eb,
                        data.dg
                    ],

                    backgroundColor:[
                        '#28a745',
                        '#fd7e14'
                    ]

                }]
            }

        }
    );

});

</script>

<!-- Active Alarms -->

<script>

function loadAlarms()
{
    fetch('api/alarmdata.php')

    .then(response => response.json())

    .then(data => {

        let html = '';

        data.forEach(alarm => {

            html += `
            <div class="alert alert-danger">

                <strong>${alarm.parameter_name}</strong>

                | Limit: ${alarm.alarm_value}

                | Actual: ${alarm.actual_value}

            </div>
