import { useId, useRef, useState } from 'react';
import clsx from 'clsx';
import { Highlight } from 'prism-react-renderer';
import { darkCodeTheme } from '@site/src/codeThemes';
import Icon from '@site/src/components/Icon';
import styles from './FeatureFile.module.css';

const STEP = /^\s*(Given|When|Then|And|But)\s/;

const SCENARIO = /^\s*Scenario( Outline)?:/;

export const isStep = (line) => STEP.test(line);

const counted = (count, noun) => `${count} ${noun}${count === 1 ? '' : 's'}`;

// The 2 lines Behat prints after a run in which every scenario passed.
export const summarize = (code) => {
  const lines = code.split('\n');
  const scenarios = lines.filter((line) => SCENARIO.test(line)).length;
  const steps = lines.filter(isStep).length;

  return [`${counted(scenarios, 'scenario')} (${scenarios} passed)`, `${counted(steps, 'step')} (${steps} passed)`];
};

// Draws the highlighted lines, with a status mark beside each step. A step's
// position sets the delay before its mark shows it passed, and a line's
// indentation sets where its wrapped part starts.
const Lines = ({ tokens, getTokenProps }) => {
  let steps = 0;

  return tokens.map((tokensOfLine, index) => {
    // An empty line comes as 1 newline token, which would add a line break.
    const line = tokensOfLine.length === 1 && tokensOfLine[0].content === '\n' ? [] : tokensOfLine;
    const text = line.map((token) => token.content).join('');
    const step = isStep(text) ? steps++ : null;

    return (
      // The lines never reorder, so the key is their position.
      <span key={index} className={clsx('token-line', styles.line)} style={{ '--indent': text.length - text.trimStart().length, '--step': step ?? undefined }}>
        <span className={clsx(styles.status, step !== null && styles.passed)} aria-hidden="true">
          {step !== null && <Icon name="check" className={styles.statusIcon} />}
        </span>
        <span className={styles.text}>
          {line.map((token, position) => (
            <span key={position} {...getTokenProps({ token })} />
          ))}
        </span>
      </span>
    );
  });
};

// Shows 1 feature file at a time, chosen with tabs. Every file is drawn in
// the same place and only the chosen one shows, so the window takes the
// height of the tallest file and switching files doesn't move the page.
const FeatureFile = ({ files, label }) => {
  const id = useId();
  const tabs = useRef([]);
  const [active, setActive] = useState(0);
  const keyTargets = { ArrowLeft: active - 1, ArrowRight: active + 1, Home: 0, End: files.length - 1 };

  const moveTo = (event) => {
    if (!Object.prototype.hasOwnProperty.call(keyTargets, event.key)) {
      return;
    }

    const next = (keyTargets[event.key] + files.length) % files.length;

    event.preventDefault();
    setActive(next);
    tabs.current[next].focus();
  };

  return (
    <figure className={styles.featureFile}>
      <div className={styles.window} style={{ '--code-color': darkCodeTheme.plain.color, '--code-background': darkCodeTheme.plain.backgroundColor }}>
        <div className={styles.bar}>
          <span className={styles.bars} aria-hidden="true">
            <span />
            <span />
            <span />
            <span />
          </span>
          <div className={styles.tabs} role="tablist" aria-label={label}>
            {files.map((item, index) => (
              <button
                key={item.name}
                ref={(element) => {
                  tabs.current[index] = element;
                }}
                id={`${id}-tab-${index}`}
                type="button"
                role="tab"
                className={styles.tab}
                aria-selected={index === active}
                aria-controls={`${id}-panel-${index}`}
                tabIndex={index === active ? 0 : -1}
                onClick={() => setActive(index)}
                onKeyDown={moveTo}
              >
                {item.name}
              </button>
            ))}
          </div>
        </div>
        <div className={styles.panels}>
          {files.map((item, index) => (
            <Highlight key={item.name} theme={darkCodeTheme} code={item.code} language="gherkin">
              {({ className, tokens, getTokenProps }) => (
                <div
                  id={`${id}-panel-${index}`}
                  className={clsx(styles.panel, index === active && styles.active)}
                  role="tabpanel"
                  aria-labelledby={`${id}-tab-${index}`}
                  aria-hidden={index === active ? undefined : true}
                  tabIndex={index === active ? 0 : undefined}
                  style={{ '--steps': item.code.split('\n').filter(isStep).length }}
                >
                  <pre className={clsx(className, styles.code)}>
                    <code>
                      <Lines tokens={tokens} getTokenProps={getTokenProps} />
                    </code>
                  </pre>
                  <p className={styles.summary}>
                    {summarize(item.code).map((line) => (
                      <span key={line}>{line}</span>
                    ))}
                  </p>
                </div>
              )}
            </Highlight>
          ))}
        </div>
      </div>
      <figcaption className={styles.caption}>
        <Icon name="arrowRight" className={styles.captionIcon} />
        <span>{files[active].caption}</span>
      </figcaption>
    </figure>
  );
};

export default FeatureFile;
