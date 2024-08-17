jQuery(document).ready(function($) {
    var taskList = $('#tasks-list');

    // Add new task
    $('#add-task-button').click(function() {
        var taskName = $('#new-task').val();
        if (taskName) {
            var timestamp = Math.floor(Date.now() / 1000); // Get current timestamp in seconds
            addTaskToList(taskName, timestamp);
            $('#new-task').val('');
            saveTasks();
        }
    });

    // Remove task
    taskList.on('click', '.remove-task', function() {
        $(this).closest('tr').remove();
        saveTasks();
    });

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

        $.post(ajax_object.ajax_url, {
            action: 'save_tasks',
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
});