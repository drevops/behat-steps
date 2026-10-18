import { themes } from 'prism-react-renderer';
import { darkCodeTheme, lightCodeTheme } from '../../src/codeThemes';

// WCAG relative luminance of a '#rrggbb' or 'rgb(r, g, b)' color.
const luminance = (color) => {
  const channels = color.startsWith('#') ? color.slice(1).match(/../g).map((hex) => parseInt(hex, 16)) : color.match(/\d+/g).map(Number);

  const [red, green, blue] = channels.map((channel) => {
    const value = channel / 255;

    return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
  });

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
};

const contrast = (first, second) => {
  const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);

  return (lighter + 0.05) / (darker + 0.05);
};

// Merges the entries of each token type the way prism-react-renderer does,
// with later entries overriding earlier ones.
const tokenStyles = (theme) => theme.styles.reduce((styles, { types, style }) => {
  types.forEach((type) => {
    styles[type] = { ...styles[type], ...style };
  });

  return styles;
}, {});

describe.each([
  { name: 'light', theme: lightCodeTheme, base: themes.github },
  { name: 'dark', theme: darkCodeTheme, base: themes.dracula },
])('$name code theme', ({ theme, base }) => {
  const { color, backgroundColor } = theme.plain;

  test('keeps the plain colors of its base theme', () => {
    expect(theme.plain).toEqual(base.plain);
  });

  test('draws plain text at 4.5:1 or more', () => {
    expect(contrast(color, backgroundColor)).toBeGreaterThanOrEqual(4.5);
  });

  test.each(Object.entries(tokenStyles(theme)))('draws %s tokens at 4.5:1 or more, fully opaque', (type, style) => {
    expect(contrast(style.color || color, backgroundColor)).toBeGreaterThanOrEqual(4.5);
    expect(style.opacity ?? 1).toBe(1);
  });
});
