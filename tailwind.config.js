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
          ink: '#10243b',
          muted: '#63768a',
          teal: '#1f9a91',
          aqua: '#6fd7e8',
          mint: '#dff7ef',
          mist: '#f4f9fb',
          navy: '#12385a',
        },
      },
      boxShadow: {
        glass: '0 28px 70px rgba(16, 36, 59, 0.12)',
        soft: '0 18px 44px rgba(16, 36, 59, 0.08)',
      },
      fontFamily: {
        prompt: ['Prompt', 'sans-serif'],
        sarabun: ['Sarabun', 'sans-serif'],
      },
    },
  },
  plugins: [],
};
