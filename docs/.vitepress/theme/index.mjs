import DefaultTheme from 'vitepress/theme';
import { onMounted, watch, nextTick } from 'vue';
import { useRoute } from 'vitepress';
import './custom.css';

export default {
  extends: DefaultTheme,
  setup() {
    const route = useRoute();

    const loadMermaid = () => {
      return new Promise((resolve) => {
        if (typeof window === 'undefined') return resolve(null);
        if (window.mermaid) return resolve(window.mermaid);

        const existing = document.querySelector('script[src*="mermaid"]');
        if (existing) {
          if (window.mermaid) return resolve(window.mermaid);
          existing.addEventListener('load', () => resolve(window.mermaid));
          setTimeout(() => resolve(window.mermaid || null), 2000);
          return;
        }

        const script = document.createElement('script');
        script.src = '/docs/js/mermaid.min.js';
        script.onload = () => resolve(window.mermaid);
        script.onerror = () => {
          const cdn = document.createElement('script');
          cdn.src = 'https://cdn.jsdelivr.net/npm/mermaid@10/dist/mermaid.min.js';
          cdn.onload = () => resolve(window.mermaid);
          cdn.onerror = () => resolve(null);
          document.head.appendChild(cdn);
        };
        document.head.appendChild(script);
      });
    };

    const initMermaid = async () => {
      if (typeof window === 'undefined') return;

      try {
        const mermaid = await loadMermaid();
        if (!mermaid) {
          console.warn('[VitePress Mermaid] Mermaid library not available.');
          return;
        }

        mermaid.initialize({
          startOnLoad: false,
          theme: 'neutral',
          securityLevel: 'loose',
          fontFamily: 'Inter, system-ui, -apple-system, sans-serif',
          themeVariables: {
            primaryColor: '#e0f2fe',
            primaryTextColor: '#0369a1',
            primaryBorderColor: '#0284c7',
            lineColor: '#64748b',
            secondaryColor: '#f1f5f9',
            tertiaryColor: '#ffffff',
          }
        });

        await nextTick();

        const mermaidBlocks = document.querySelectorAll(
          'div[class*="language-mermaid"]:not([data-processed="true"]), pre[class*="language-mermaid"]:not([data-processed="true"])'
        );

        for (let i = 0; i < mermaidBlocks.length; i++) {
          const el = mermaidBlocks[i];
          el.setAttribute('data-processed', 'true');
          const code = el.querySelector('code')?.innerText || el.innerText;

          if (!code || !code.trim()) continue;

          const cleanCode = code
            .trim()
            .replace(/&lt;/g, '<')
            .replace(/&gt;/g, '>')
            .replace(/&amp;/g, '&');

          try {
            const id = `mermaid-svg-${Date.now()}-${i}-${Math.random().toString(36).substring(2, 7)}`;
            const { svg } = await mermaid.render(id, cleanCode);

            const wrapper = document.createElement('div');
            wrapper.className = 'mermaid-diagram-wrapper';
            wrapper.innerHTML = svg;
            wrapper.style.display = 'flex';
            wrapper.style.justifyContent = 'center';
            wrapper.style.margin = '1.75rem 0';
            wrapper.style.overflowX = 'auto';
            wrapper.style.padding = '1.25rem';
            wrapper.style.background = '#f8fafc';
            wrapper.style.borderRadius = '8px';
            wrapper.style.border = '1px solid #e2e8f0';
            wrapper.style.boxShadow = '0 1px 3px rgba(0, 0, 0, 0.05)';

            el.parentNode?.replaceChild(wrapper, el);
          } catch (err) {
            console.warn('[VitePress Mermaid] Failed to render diagram:', err);
          }
        }
      } catch (e) {
        console.warn('[VitePress Mermaid] Error in initMermaid:', e);
      }
    };

    onMounted(() => {
      initMermaid();
    });

    watch(
      () => route.path,
      () => {
        setTimeout(initMermaid, 250);
      }
    );
  }
};
