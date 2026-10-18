import { themes } from 'prism-react-renderer';

// prism-react-renderer merges the styles of a token type in order, so each
// entry appended here replaces a color below 4.5:1 on the theme's background.
export const lightCodeTheme = {
  ...themes.github,
  styles: [
    ...themes.github.styles,
    { types: ['comment', 'prolog', 'doctype', 'cdata'], style: { color: '#6a6a5c' } },
    { types: ['namespace'], style: { opacity: 1 } },
    { types: ['string', 'attr-value'], style: { color: '#c80f5f' } },
    { types: ['entity', 'url', 'symbol', 'number', 'boolean', 'variable', 'constant', 'property', 'regex', 'inserted'], style: { color: '#17716f' } },
    { types: ['atrule', 'attr-name'], style: { color: '#006c99' } },
    { types: ['function', 'deleted'], style: { color: '#c4303d' } },
  ],
};

export const darkCodeTheme = {
  ...themes.dracula,
  styles: [
    ...themes.dracula.styles,
    { types: ['comment'], style: { color: '#8b99cc' } },
  ],
};
