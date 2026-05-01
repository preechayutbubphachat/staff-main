/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './index.php',
    './pages/dashboard.php',
    './pages/time.php',
    './pages/approval_queue.php',
    './pages/daily_schedule.php',
    './pages/my_reports.php',
    './pages/department_reports.php',
    './pages/profile.php',
    './pages/db_admin_dashboard.php',
    './pages/db_table_browser.php',
    './pages/db_change_logs.php',
    './includes/navigation.php',
    './partials/time/**/*.php',
    './partials/approval/**/*.php',
    './partials/manage_time_logs/**/*.php',
    './partials/reports/**/*.php',
    './partials/admin/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        hospital: {
          ink: '#082B45',
          muted: '#64748B',
          teal: '#0F9F95',
          aqua: '#6fd7e8',
          mint: '#DDF8F3',
          mist: '#EAF7F8',
          navy: '#063B4F',
        },
      },
      boxShadow: {
        glass: '0 28px 70px rgba(6, 59, 79, 0.13)',
        soft: '0 18px 44px rgba(6, 59, 79, 0.08)',
      },
      fontFamily: {
        prompt: ['Prompt', 'sans-serif'],
        sarabun: ['Sarabun', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
