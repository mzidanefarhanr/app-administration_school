<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Subfase 12a (DEC-45 D3):
     * Migrasi aditif penambahan indeks komposit terukur pada tabel dengan volume kueri tinggi.
     */
    public function up(): void
    {
        if (Schema::hasTable('students') && ! Schema::hasIndex('students', 'students_deleted_at_name_idx')) {
            Schema::table('students', function (Blueprint $table) {
                $table->index(['deleted_at', 'name'], 'students_deleted_at_name_idx');
            });
        }

        if (Schema::hasTable('student_rombels')) {
            Schema::table('student_rombels', function (Blueprint $table) {
                if (! Schema::hasIndex('student_rombels', 'sr_rombel_status_active_idx')) {
                    $table->index(['school_rombel', 'student_status_id', 'deleted_at', 'archived_at'], 'sr_rombel_status_active_idx');
                }
                if (! Schema::hasIndex('student_rombels', 'sr_student_rombel_deleted_idx')) {
                    $table->index(['student_id', 'school_rombel', 'deleted_at'], 'sr_student_rombel_deleted_idx');
                }
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (! Schema::hasIndex('employees', 'employees_user_status_deleted_idx')) {
                    $table->index(['user_id', 'employee_status_id', 'deleted_at'], 'employees_user_status_deleted_idx');
                }
                if (! Schema::hasIndex('employees', 'employees_type_status_deleted_idx')) {
                    $table->index(['employee_type_id', 'employee_status_id', 'deleted_at'], 'employees_type_status_deleted_idx');
                }
            });
        }

        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                if (! Schema::hasIndex('letters', 'letters_dir_status_workflow_idx')) {
                    $table->index(['direction', 'status', 'workflow_status'], 'letters_dir_status_workflow_idx');
                }
                if (! Schema::hasIndex('letters', 'letters_dir_cat_status_idx')) {
                    $table->index(['direction', 'category', 'status'], 'letters_dir_cat_status_idx');
                }
            });
        }

        if (Schema::hasTable('letter_dispositions') && ! Schema::hasIndex('letter_dispositions', 'ld_letter_recipient_status_idx')) {
            Schema::table('letter_dispositions', function (Blueprint $table) {
                $table->index(['letter_id', 'recipient_employee_id', 'status'], 'ld_letter_recipient_status_idx');
            });
        }

        if (Schema::hasTable('leave_requests') && ! Schema::hasIndex('leave_requests', 'lr_subj_status_start_idx')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->index(['subject_type', 'status', 'start_date'], 'lr_subj_status_start_idx');
            });
        }

        if (Schema::hasTable('archive_documents') && ! Schema::hasIndex('archive_documents', 'ad_cat_archived_valid_idx')) {
            Schema::table('archive_documents', function (Blueprint $table) {
                $table->index(['category', 'archived_at', 'valid_until'], 'ad_cat_archived_valid_idx');
            });
        }

        if (Schema::hasTable('operator_reminders') && ! Schema::hasIndex('operator_reminders', 'op_reminders_status_prio_due_idx')) {
            Schema::table('operator_reminders', function (Blueprint $table) {
                $table->index(['status', 'priority', 'due_date'], 'op_reminders_status_prio_due_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('operator_reminders') && Schema::hasIndex('operator_reminders', 'op_reminders_status_prio_due_idx')) {
            Schema::table('operator_reminders', function (Blueprint $table) {
                $table->dropIndex('op_reminders_status_prio_due_idx');
            });
        }

        if (Schema::hasTable('archive_documents') && Schema::hasIndex('archive_documents', 'ad_cat_archived_valid_idx')) {
            Schema::table('archive_documents', function (Blueprint $table) {
                $table->dropIndex('ad_cat_archived_valid_idx');
            });
        }

        if (Schema::hasTable('leave_requests') && Schema::hasIndex('leave_requests', 'lr_subj_status_start_idx')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropIndex('lr_subj_status_start_idx');
            });
        }

        if (Schema::hasTable('letter_dispositions') && Schema::hasIndex('letter_dispositions', 'ld_letter_recipient_status_idx')) {
            Schema::table('letter_dispositions', function (Blueprint $table) {
                $table->dropIndex('ld_letter_recipient_status_idx');
            });
        }

        if (Schema::hasTable('letters')) {
            Schema::table('letters', function (Blueprint $table) {
                if (Schema::hasIndex('letters', 'letters_dir_status_workflow_idx')) {
                    $table->dropIndex('letters_dir_status_workflow_idx');
                }
                if (Schema::hasIndex('letters', 'letters_dir_cat_status_idx')) {
                    $table->dropIndex('letters_dir_cat_status_idx');
                }
            });
        }

        if (Schema::hasTable('employees')) {
            Schema::table('employees', function (Blueprint $table) {
                if (Schema::hasIndex('employees', 'employees_user_status_deleted_idx')) {
                    $table->dropIndex('employees_user_status_deleted_idx');
                }
                if (Schema::hasIndex('employees', 'employees_type_status_deleted_idx')) {
                    $table->dropIndex('employees_type_status_deleted_idx');
                }
            });
        }

        if (Schema::hasTable('student_rombels')) {
            Schema::table('student_rombels', function (Blueprint $table) {
                if (Schema::hasIndex('student_rombels', 'sr_rombel_status_active_idx')) {
                    $table->dropIndex('sr_rombel_status_active_idx');
                }
                if (Schema::hasIndex('student_rombels', 'sr_student_rombel_deleted_idx')) {
                    $table->dropIndex('sr_student_rombel_deleted_idx');
                }
            });
        }

        if (Schema::hasTable('students') && Schema::hasIndex('students', 'students_deleted_at_name_idx')) {
            Schema::table('students', function (Blueprint $table) {
                $table->dropIndex('students_deleted_at_name_idx');
            });
        }
    }
};
