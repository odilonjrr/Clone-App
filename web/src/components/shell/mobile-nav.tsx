"use client";

import { Menu } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogTrigger } from "@/components/ui/dialog";
import { SideNav } from "./side-nav";

export function MobileNav({ unread }: { unread?: number }) {
  const [open, setOpen] = useState(false);
  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="ghost" size="icon" aria-label="Abrir menu" className="lg:hidden">
          <Menu />
        </Button>
      </DialogTrigger>
      <DialogContent side="drawer" title="Menu">
        <SideNav unread={unread} onNavigate={() => setOpen(false)} className="-m-3 w-auto" />
      </DialogContent>
    </Dialog>
  );
}
