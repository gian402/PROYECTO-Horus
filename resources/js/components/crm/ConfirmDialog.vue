<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

withDefaults(
    defineProps<{
        title: string;
        description?: string;
        confirmLabel?: string;
        destructive?: boolean;
        processing?: boolean;
    }>(),
    { confirmLabel: 'Confirmar', destructive: false, processing: false },
);

const emit = defineEmits<{ confirm: [] }>();
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger v-if="$slots.trigger" as-child>
            <slot name="trigger" />
        </DialogTrigger>
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">{{
                    description
                }}</DialogDescription>
            </DialogHeader>
            <slot />
            <DialogFooter class="gap-2">
                <DialogClose as-child>
                    <Button variant="outline" :disabled="processing"
                        >Cancelar</Button
                    >
                </DialogClose>
                <Button
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="processing"
                    @click="emit('confirm')"
                    >{{ confirmLabel }}</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
