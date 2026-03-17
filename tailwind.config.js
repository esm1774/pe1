/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        "./index.php",
        "./register.html",
        "./post.php",
        "./admin/index.html",
        "./admin/js/admin.js",
    ],
    theme: {
        extend: {
            colors: {
                emerald: {
                    50: '#ecfdf5', 100: '#d1fae5', 200: '#a7f3d0', 300: '#6ee7b7',
                    400: '#34d399', 500: '#10b981', 600: '#059669', 700: '#047857',
                    800: '#065f46', 900: '#064e3b',
                },
                slate: {
                    850: '#162032',
                    900: '#0f172a',
                    950: '#020617'
                }
            },
            fontFamily: { cairo: ['Cairo', 'sans-serif'] },
            animation: {
                'float': 'float 6s ease-in-out infinite',
                'blob': 'blob 7s infinite',
            },
            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-20px)' },
                },
                blob: {
                    '0%': { transform: 'translate(0px, 0px) scale(1)' },
                    '33%': { transform: 'translate(30px, -50px) scale(1.1)' },
                    '66%': { transform: 'translate(-20px, 20px) scale(0.9)' },
                    '100%': { transform: 'translate(0px, 0px) scale(1)' },
                }
            }
        },
    },
    plugins: [],
    // RTL Support
    corePlugins: {
        padding: true,
        margin: true,
    }
}
