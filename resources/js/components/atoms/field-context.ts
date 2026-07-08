import { createContext } from 'react';

/**
 * Contexto de campo do Veludo. O FormField publica o estado de erro e o id da
 * mensagem; inputs descendentes (mesmo aninhados) leem daqui para ficar em
 * vermelho e se ligar ao erro via aria-describedby, sem prop drilling.
 */
export interface FieldContextValue {
    invalid: boolean;
    errorId?: string;
}

export const FieldContext = createContext<FieldContextValue>({ invalid: false });
