/**
 * Student Attendance Management System
 * Main JavaScript File
 */

// Show loading spinner for AJAX requests
$(document).ajaxStart(function() {
    $('<div class="loading-spinner"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>')
        .appendTo('body');
}).ajaxStop(function() {
    $('.loading-spinner').remove();
});

/**
 * Mark attendance via AJAX
 * @param {number} studentId - The student ID
 * @param {string} status - Attendance status (present, absent, late)
 * @param {string} date - The attendance date
 * @param {number} subjectId - The subject ID
 */
function markAttendance(studentId, status, date, subjectId) {
    $.ajax({
        url: '../ajax/mark-attendance.php',
        type: 'POST',
        data: {
            student_id: studentId,
            status: status,
            date: date,
            subject_id: subjectId
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Update UI
                const statusCell = $('#status-' + studentId);
                statusCell.removeClass('bg-success bg-danger bg-warning');
                
                switch(status) {
                    case 'present':
                        statusCell.addClass('bg-success');
                        statusCell.html('<i class="fas fa-check-circle"></i> Present');
                        break;
                    case 'absent':
                        statusCell.addClass('bg-danger');
                        statusCell.html('<i class="fas fa-times-circle"></i> Absent');
                        break;
                    case 'late':
                        statusCell.addClass('bg-warning');
                        statusCell.html('<i class="fas fa-clock"></i> Late');
                        break;
                }
                
                // Show success notification
                Swal.fire({
                    icon: 'success',
                    title: 'Updated!',
                    text: 'Attendance marked successfully',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            } else {
                // Show error notification
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message || 'Failed to mark attendance',
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Server error occurred. Please try again.',
            });
        }
    });
}

/**
 * Delete confirmation dialog
 * @param {string} url - The URL to redirect to after confirmation
 * @param {string} title - The dialog title
 * @param {string} text - The dialog text
 */
function confirmDelete(url, title = 'Are you sure?', text = 'This action cannot be undone.') {
    Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = url;
        }
    });
}

/**
 * Load attendance chart
 * @param {string} elementId - The canvas element ID
 * @param {Object} data - The chart data
 * @param {string} type - Chart type (bar, pie, etc.)
 */
function loadAttendanceChart(elementId, data, type = 'bar') {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    new Chart(ctx, {
        type: type,
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

/**
 * Print report
 * @param {string} elementId - The ID of the element to print
 */
function printReport(elementId) {
    const printContents = document.getElementById(elementId).innerHTML;
    const originalContents = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div class="print-header">
            <h1 class="text-center">${SITE_NAME}</h1>
            <h2 class="text-center">Attendance Report</h2>
            <p class="text-center">Generated on: ${new Date().toLocaleDateString()}</p>
        </div>
        ${printContents}
    `;
    
    window.print();
    document.body.innerHTML = originalContents;
}

// Document ready function
$(document).ready(function() {
    
    // Initialize date picker
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            todayHighlight: true
        });
    }
    
    // Form validation
    $('.needs-validation').submit(function(event) {
        if (this.checkValidity() === false) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        $(this).addClass('was-validated');
    });
    
    // Toggle password visibility
    $('.toggle-password').click(function() {
        const passwordField = $($(this).data('target'));
        const type = passwordField.attr('type');
        
        if (type === 'password') {
            passwordField.attr('type', 'text');
            $(this).html('<i class="fas fa-eye-slash"></i>');
        } else {
            passwordField.attr('type', 'password');
            $(this).html('<i class="fas fa-eye"></i>');
        }
    });
    
    // Cascading dropdowns for class and subject
    $('#class_id').change(function() {
        const classId = $(this).val();
        
        if (classId) {
            $.ajax({
                url: '../ajax/get-subjects.php',
                type: 'POST',
                data: { class_id: classId },
                dataType: 'json',
                success: function(data) {
                    let options = '<option value="">Select Subject</option>';
                    
                    $.each(data, function(key, value) {
                        options += '<option value="' + value.id + '">' + value.name + '</option>';
                    });
                    
                    $('#subject_id').html(options);
                    $('#subject_id').prop('disabled', false);
                }
            });
        } else {
            $('#subject_id').html('<option value="">Select Subject</option>');
            $('#subject_id').prop('disabled', true);
        }
    });
    
    // Handle quick attendance marking buttons
    $('.btn-quick-mark').click(function() {
        const studentId = $(this).data('student-id');
        const status = $(this).data('status');
        const date = $(this).data('date');
        const subjectId = $(this).data('subject-id');
        
        markAttendance(studentId, status, date, subjectId);
    });
    
    // Live search for student lists
    $('#studentSearch').keyup(function() {
        const value = $(this).val().toLowerCase();
        
        $('#studentTable tbody tr').filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
