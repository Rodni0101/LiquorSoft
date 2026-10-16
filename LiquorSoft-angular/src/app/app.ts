import { Component, ElementRef, ViewChild, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { RouterOutlet } from '@angular/router';
import { CurrencyPipe } from '@angular/common';
import { CartService } from './cart.service';

interface ChatProduct {
  id: number; name: string; category: string; description: string;
  price: number; stock: number; icon: string;
}

interface ChatMessage { role: 'assistant' | 'user'; text: string; products?: ChatProduct[]; }

@Component({
  imports: [RouterOutlet, CurrencyPipe],
  selector: 'app-root',
  styleUrl: './app.css',
  templateUrl: './app.html',
})
export class App {
  protected readonly title = signal('LiquorSoft-angular');
  private readonly http = inject(HttpClient);
  private readonly cart = inject(CartService);
  @ViewChild('chatMessages') private chatMessages?: ElementRef<HTMLElement>;
  protected readonly chatOpen = signal(false);
  protected readonly chatBusy = signal(false);
  protected readonly showMoreQuickQuestions = signal(false);
  protected readonly chatInput = signal('');
  protected readonly chatMessagesList = signal<ChatMessage[]>([
    { role: 'assistant', text: '¡Hola! Soy el asistente de LiquorSoft. Puedo buscar productos disponibles, precios, categorías y pedidos de tu cuenta.' },
  ]);
  protected readonly quickQuestions = [
    'Ver productos disponibles',
    '¿Cuántos productos hay?',
    'Buscar whisky',
    'Buscar ron',
    'Buscar vinos',
    'Buscar cervezas',
    'Productos por menos de $100.000',
    'Consultar mis pedidos',
    'Envíos y horarios',
  ];

  protected askQuickQuestion(question: string): void {
    this.showMoreQuickQuestions.set(false);
    this.chatInput.set(question);
    this.sendChatMessage();
  }

  protected toggleQuickQuestions(): void {
    this.showMoreQuickQuestions.update(show => !show);
  }

  protected sendChatMessage(event?: Event): void {
    event?.preventDefault();
    const question = this.chatInput().trim();
    if (!question || this.chatBusy()) return;
    this.chatInput.set('');
    this.chatMessagesList.update(messages => [...messages, { role: 'user', text: question }]);
    this.chatBusy.set(true);
    this.http.post<{ answer: string; products: ChatProduct[] }>('/api/chat.php', { message: question }).subscribe({
      next: (response) => {
        this.chatMessagesList.update(messages => [...messages, { role: 'assistant', text: response.answer, products: response.products }]);
        this.chatBusy.set(false);
        this.scrollChat();
      },
      error: (error) => {
        const text = error.status === 429 ? 'Espera un momento antes de enviar otra pregunta.' : error.status === 503 ? 'El catálogo está temporalmente desconectado.' : 'No pude consultar el catálogo. Intenta de nuevo en un momento.';
        this.chatMessagesList.update(messages => [...messages, { role: 'assistant', text }]);
        this.chatBusy.set(false);
        this.scrollChat();
      },
    });
    this.scrollChat();
  }

  protected setChatInput(event: Event): void {
    this.chatInput.set((event.target as HTMLInputElement).value);
  }

  protected addChatProduct(product: ChatProduct): void {
    const added = this.cart.add({ id: product.id, name: product.name, price: product.price, icon: product.icon, stock: product.stock });
    this.chatMessagesList.update(messages => [...messages, { role: 'assistant', text: added ? `${product.name} se agregó al carrito.` : `${product.name} no tiene unidades disponibles.` }]);
    this.scrollChat();
  }

  private scrollChat(): void {
    setTimeout(() => { const element = this.chatMessages?.nativeElement; if (element) element.scrollTop = element.scrollHeight; });
  }
}
