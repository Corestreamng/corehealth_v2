<script>
    $(function() {
        $('#vitals_history').DataTable({
            "dom": 'Bfrtip',
            "iDisplayLength": 50,
            "lengthMenu": [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            "buttons": ['pageLength', 'copy', 'excel', 'csv', 'pdf', 'print', 'colvis'],
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "{{ route('patient-vitals', $patient->id) }}",
                "type": "GET"
            },
            "columns": [{
                    data: "DT_RowIndex",
                    name: "DT_RowIndex"
                },
                {
                    data: "created_at",
                    name: "created_at"
                },
                {
                    data: "result",
                    name: "result"
                },
            ],

            "paging": true
        });
    });
</script>
<script>
    $.ajax({
        url: "{{ route('allPatientVitals', $patient->id) }}",
        type: "GET",
        dataType: "json",
        success: function(response) {
            console.log("Vitals fetched successfully:", response);
            // response = JSON.parse(response);
            // Extract vital sign data
            const timeTaken = response.map(item => item.time_taken);
            const bloodPressureSystolic = response.map(item => parseInt(item.blood_pressure.split("/")[0]));
            const bloodPressureDiastolic = response.map(item => parseInt(item.blood_pressure.split("/")[
                1]));
            const temperature = response.map(item => parseFloat(item.temp));
            const weight = response.map(item => parseFloat(item.weight));
            const heartRate = response.map(item => parseInt(item.heart_rate));
            const respRate = response.map(item => parseInt(item.resp_rate));

            // Create the Blood Pressure Chart (with null check)
            var bpChartEl = document.getElementById("bloodPressureChart");
            if (bpChartEl) {
            new Chart(bpChartEl, {
                type: "line",
                data: {
                    labels: timeTaken,
                    datasets: [{
                            label: "Systolic Blood Pressure",
                            borderColor: "rgba(255, 99, 132, 1)",
                            backgroundColor: "rgba(255, 99, 132, 0.2)",
                            data: bloodPressureSystolic,
                            lineTension: 0.4
                        },
                        {
                            label: "Diastolic Blood Pressure",
                            borderColor: "rgba(54, 162, 235, 1)",
                            backgroundColor: "rgba(54, 162, 235, 0.2)",
                            data: bloodPressureDiastolic,
                            lineTension: 0.4
                        },
                    ],
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: "Time Taken",
                            },
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: "Blood Pressure",
                            },
                        },
                    },
                },
            });
            } // end if bpChartEl

            // Create the Weight Chart (with null check)
            var weightChartEl = document.getElementById("weightChart");
            if (weightChartEl) {
            new Chart(weightChartEl, {
                type: "line",
                data: {
                    labels: timeTaken,
                    datasets: [{
                        label: "Weight",
                        borderColor: "rgba(255, 206, 86, 1)",
                        backgroundColor: "rgba(255, 206, 86, 0.2)",
                        data: weight,
                        lineTension: 0.4
                    }, ],
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: "Time Taken",
                            },
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: "Weight",
                            },
                        },
                    },
                },
            });
            } // end if weightChartEl

            // Create the Temperature Chart (with null check)
            var tempChartEl = document.getElementById("temperatureChart");
            if (tempChartEl) {
            new Chart(tempChartEl, {
                type: "line",
                data: {
                    labels: timeTaken,
                    datasets: [{
                        label: "Temperature",
                        borderColor: "rgba(255, 206, 86, 1)",
                        backgroundColor: "rgba(255, 206, 86, 0.2)",
                        data: temperature,
                        lineTension: 0.4
                    }, ],
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: "Time Taken",
                            },
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: "Temperature",
                            },
                        },
                    },
                },
            });
            } // end if tempChartEl

            // Create the Heart Rate Chart (with null check)
            var hrChartEl = document.getElementById("heartRateChart");
            if (hrChartEl) {
            new Chart(hrChartEl, {
                type: "line",
                data: {
                    labels: timeTaken,
                    datasets: [{
                        label: "Heart Rate",
                        borderColor: "rgba(75, 192, 192, 1)",
                        backgroundColor: "rgba(75, 192, 192, 0.2)",
                        data: heartRate,
                        lineTension: 0.4
                    }, ],
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: "Time Taken",
                            },
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: "Heart Rate",
                            },
                        },
                    },
                },
            });
            } // end if hrChartEl

            // Create the Respiratory Rate Chart (with null check)
            var respChartEl = document.getElementById("respRateChart");
            if (respChartEl) {
            new Chart(respChartEl, {
                type: "line",
                data: {
                    labels: timeTaken,
                    datasets: [{
                        label: "Respiratory Rate",
                        borderColor: "rgba(153, 102, 255, 1)",
                        backgroundColor: "rgba(153, 102, 255, 0.2)",
                        data: respRate,
                        lineTension: 0.4
                    }, ],
                },
                options: {
                    responsive: true,
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: "Time Taken",
                            },
                        },
                        y: {
                            display: true,
                            title: {
                                display: true,
                                text: "Respiratory Rate",
                            },
                        },
                    },
                },
            });
            } // end if respChartEl
        },
        error: function(xhr, status, error) {
            // Error handler: handle the error
            console.error("Error fetching vitals:", error);
            // Add your code to handle the error here
        }
    });
</script>
