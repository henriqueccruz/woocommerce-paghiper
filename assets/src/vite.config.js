import { defineConfig } from 'vite';
import viteImagemin from 'vite-plugin-imagemin';
import { viteStaticCopy } from 'vite-plugin-static-copy';
import { resolve } from 'path';

export default defineConfig(({ command, mode }) => {
    const isProduction = mode === 'production';
    const isDevelopment = mode === 'development';

    return {
        base: '/',
        build: {
            outDir: '../dist',
            emptyOutDir: false,
            minify: isProduction,
            sourcemap: isDevelopment,
            rollupOptions: {
                input: {
                    'js/frontend.min': './js/interface/frontend.js',
                    'js/backend.min': './js/interface/backend.js',
                    'js/admin.min': './js/interface/admin.js',
                    'css/frontend.min': './scss/frontend.scss',
                    'css/backend.min': './scss/backend.scss',
                    'css/admin.min': './scss/admin.scss'
                },
                output: {
                    entryFileNames: '[name].js',
                    assetFileNames: '[name][extname]'
                }
            }
        },

        server: {
            host: 'wordpress.sandbox.local',
            https: {
                key: './certificates/wordpress.sandbox.local+3-key.pem',
                cert: './certificates/wordpress.sandbox.local+3.pem'
            },
            watch: {
                include: [
                    './js/**/*.{js,jsx}',
                    './scss/**/*.scss',
                    '../../../**/*.php'
                ],
                exclude: [
                    'node_modules/**',
                    'dist/**',
                    '**/.git/**'
                ]
            },
            proxy: {
                '^(?!/(@vite|node_modules|src))': {
                    target: 'https://wordpress.sandbox.local',
                    secure: false,
                    changeOrigin: true
                }
            },
            cors: true,
            hmr: {
                protocol: 'wss',
                host: 'wordpress.sandbox.local',
                clientPort: 5173
            }
        },

        plugins: [

            viteImagemin({
                disable: !isProduction,
                gifsicle: {
                    optimizationLevel: 3
                },
                mozjpeg: {
                    quality: 85
                },
                pngquant: {
                    quality: [0.8, 0.9],
                    speed: 4
                },
                svgo: {
                    plugins: [
                        { name: 'removeViewBox' },
                        { name: 'removeEmptyAttrs', active: false }
                    ]
                }
            }),
            viteStaticCopy({
                targets: [
                    {
                        src: 'js/libs/**/*',
                        dest: '../dist/js/libs'
                    },
                    {
                        src: ['fonts/*', 'php/*'],
                        dest: '../dist'
                    }
                ]
            })
        ],

        css: {
            preprocessorOptions: {
                scss: {
                    includePaths: ['node_modules']
                }
            }
        }
    };
});