declare global {
  interface Window {
    wp?: {
      editor?: {
        initialize: (id: string, settings: Record<string, unknown>) => void;
        remove: (id: string) => void;
      };
    };
    tinymce?: {
      get: (id: string) => {
        getContent: () => string;
        setContent: (content: string) => void;
        on: (event: string, callback: () => void) => void;
        removed?: boolean;
      } | null;
    };
  }
}

export {};
