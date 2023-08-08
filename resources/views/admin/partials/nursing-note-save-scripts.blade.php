<script>
    $('#observation_form').on('submit', function(e) {
        e.preventDefault();
        var the_observation_note = $('#the-observation-note').html();
        document.getElementById('observation_text').innerHTML = the_observation_note;
        this.submit();
    })

    $('#treatment_form').on('submit', function(e) {
        e.preventDefault();
        var the_observation_note = $('#the-treatment-note').html();
        document.getElementById('treatment_text').innerHTML = the_observation_note;
        this.submit();
    })

    $('#io_form').on('submit', function(e) {
        e.preventDefault();
        var the_observation_note = $('#the-io-note').html();
        document.getElementById('io_text').innerHTML = the_observation_note;
        this.submit();
    })

    // $('#others_form').on('submit', function(e) {
    //     e.preventDefault();
    //     var the_observation_note = $('#the-others-note').html();
    //     document.getElementById('others_text').innerHTML = the_observation_note;
    //     this.submit();
    // })
    $('#labour_form').on('submit', function(e) {
        e.preventDefault();
        var the_observation_note = $('#the-labour-note').html();
        document.getElementById('labour_text').innerHTML = the_observation_note;
        this.submit();
    })
</script>