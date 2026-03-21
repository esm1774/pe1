export default {
  plugins: {
    '@tailwindcss/postcss': {},
    'postcss-preset-env': {
      stage: 2,
      features: {
        'oklab-query': true,
        'color-functional-notation': true
      }
    }
  }
}
