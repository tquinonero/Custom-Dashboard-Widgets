jQuery(document).ready(function($) {
    var taskList = $('#tasks-list');

    // Add new task via button
    $('#add-task-button').on('click', function() {
        addTaskFromInput();
    });

    // Add new task via Enter key in the input
    $('#new-task').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            addTaskFromInput();
        }
    });

    // Remove task
    taskList.on('click', '.remove-task', function() {
        $(this).closest('tr').remove();
        saveTasks();
    });

    function addTaskFromInput() {
        var taskName = $('#new-task').val();
        if (!taskName) {
            return;
        }
        var timestamp = Math.floor(Date.now() / 1000); // Get current timestamp in seconds
        addTaskToList(taskName, timestamp);
        $('#new-task').val('');
        saveTasks();
    }

    function addTaskToList(taskName, timestamp) {
        var timeAgo = calculateTimeAgo(timestamp);
        taskList.append('<tr><td>' + taskName + '</td><td class="task-time" data-timestamp="' + timestamp + '">' + timeAgo + ' ago</td><td><span class="remove-task" style="cursor:pointer;color:red;">&#x2715;</span></td></tr>');
    }

    function saveTasks() {
        var tasks = [];
        $('#tasks-list tr').each(function() {
            var taskName = $(this).find('td:first').text().trim();
            var timestamp = $(this).find('.task-time').data('timestamp');
            tasks.push({ name: taskName, timestamp: timestamp });
        });

        $.post(cdw_ajax.ajax_url, {
            action: 'cdw_save_tasks',
            nonce: cdw_ajax.nonce,
            tasks: tasks
        });
    }

    function calculateTimeAgo(timestamp) {
        var timeDiff = Math.floor(Date.now() / 1000) - timestamp;
        if (timeDiff < 60) {
            return timeDiff + ' seconds';
        } else if (timeDiff < 3600) {
            return Math.floor(timeDiff / 60) + ' minutes';
        } else if (timeDiff < 86400) {
            return Math.floor(timeDiff / 3600) + ' hours';
        } else {
            return Math.floor(timeDiff / 86400) + ' days';
        }
    }

    // Periodically refresh the "time ago" labels so they stay accurate
    setInterval(function() {
        $('.task-time').each(function() {
            var $el = $(this);
            var timestamp = parseInt($el.data('timestamp'), 10);
            if (!timestamp) {
                return;
            }
            var timeAgo = calculateTimeAgo(timestamp);
            $el.text(timeAgo + ' ago');
        });
    }, 60000); // every 60 seconds
});