/**
 * Auto-refresh functionality for gym timetable
 */

jQuery(function ($) {

    const $morning = $('.morning-table');
    const $afternoon = $('.afternoon-table');

    const fadeSpeed = 600;
    const intervalTime = 10000;

    // initial state
    $morning.show();
    $afternoon.hide();

    let showingMorning = true;

    setInterval(function () {

        if (showingMorning) {
            $morning.fadeOut(fadeSpeed, function () {
                $afternoon.fadeIn(fadeSpeed);
            });
        } else {
            $afternoon.fadeOut(fadeSpeed, function () {
                $morning.fadeIn(fadeSpeed);
            });
        }

        showingMorning = !showingMorning;

    }, intervalTime);

});